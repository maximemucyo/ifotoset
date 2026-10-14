// === Cloudflare Worker: cdn.ifotoset.com proxy for object storage ===

// KEY_ID, APP_KEY and CDN_SECRET are Worker secrets (Settings → Variables and Secrets,
// or `wrangler secret put <NAME>`). In service-worker syntax they are exposed as globals.
// CDN_SECRET must match CLOUDFLARE_CDN_SECRET in the backend .env.
const BUCKET = 'ifotoset';

let authToken = '';
let downloadUrl = '';
let authExpiry = 0;

addEventListener('fetch', event => {
    event.respondWith(handle(event.request, event));
});

async function handle(request, event) {
    const { method, headers } = request;

    if (method === 'OPTIONS') {
        // CORS preflight
        return new Response(null, {
            status: 204,
            headers: corsHeaders(headers.get('Origin'))
        });
    }

    if (method !== 'GET' && method !== 'HEAD') {
        return new Response('Method Not Allowed', { status: 405 });
    }

    // Authorization & Referer check: protected galleries require valid token; public gallery assets verify allowed context
    const url = new URL(request.url);
    if (!isSafePath(url.pathname)) {
        return new Response('Bad Request', { status: 400, headers: { 'Cache-Control': 'no-store' } });
    }

    const pathToken = extractPathToken(url.pathname);
    const authResult = pathToken
        ? await verifyMediaToken(pathToken.dir + '/', pathToken.exp, pathToken.sig)
        : await isAllowedRequest(request, url);
    if (!authResult) {
        return new Response('Forbidden: direct access is not allowed or media token is invalid/expired', {
            status: 403,
            headers: {
                'Content-Type': 'text/plain; charset=utf-8',
                'Cache-Control': 'no-store'
            }
        });
    }

    try {
        // 1. Authorize with storage backend (caches authorization token in-memory)
        await authorizeStorage();

        // 2. Fetch from storage and cache/serve the asset
        return await proxyAndCache(request, event, pathToken ? pathToken.objectPath : null);
    } catch (err) {
        const cors = corsHeaders(request.headers.get('Origin'));
        cors.set('Content-Type', 'application/json; charset=utf-8');
        return new Response(JSON.stringify({ error: 'cdn_error', message: String(err) }), {
            status: 502,
            headers: cors
        });
    }
}

async function authorizeStorage() {
    // If we have a cached token that hasn't expired (tokens last 24h, we refresh at 23h), reuse it
    if (authToken && downloadUrl && Date.now() < authExpiry) return;

    const creds = btoa(`${KEY_ID}:${APP_KEY}`);
    const res = await fetch('https://api.backblazeb2.com/b2api/v2/b2_authorize_account', {
        headers: { 'Authorization': `Basic ${creds}` }
    });

    if (!res.ok) {
        throw new Error(`Storage authorization failed with status: ${res.status}`);
    }

    const data = await res.json();
    authToken = data.authorizationToken;
    downloadUrl = data.downloadUrl;
    authExpiry = Date.now() + 23 * 60 * 60 * 1000; // Refresh token after 23 hours
}

async function proxyAndCache(request, event, objectPathOverride = null) {
    const url = new URL(request.url);
    const originHeader = request.headers.get('Origin');

    // Normalize path (remove double slashes, etc.). Path-token URLs are served from the object path without the token.
    let filePath = decodeURIComponent(objectPathOverride || url.pathname).replace(/\/{2,}/g, '/');

    // Fallback: If requesting a gallery photo folder without a file extension, default to original.jpg
    const pathParts = filePath.split('/');
    const lastPart = pathParts[pathParts.length - 1];
    if (filePath.startsWith('/galleries/') && !lastPart.includes('.')) {
        filePath = `${filePath}/original.jpg`;
    }

    // Ignore any query params (and path tokens) for the cache key to maximize hits
    const cacheKeyUrl = new URL(request.url);
    cacheKeyUrl.search = '';
    if (objectPathOverride) {
        cacheKeyUrl.pathname = objectPathOverride;
    }
    const cacheKey = new Request(cacheKeyUrl.toString(), request);
    const cache = (typeof caches !== 'undefined' && caches.default) ? caches.default : null;

    const isDownload = isDownloadRequest(url);

    // 1. Check Cloudflare Edge Cache (bypass for private downloads)
    let cachedRes = null;
    if (!isDownload && cache) {
        cachedRes = await cache.match(cacheKey);
    }
    if (cachedRes) {
        // Always attach dynamic CORS headers matching current origin
        const headers = new Headers(cachedRes.headers);
        const cors = corsHeaders(originHeader);
        for (const [k, v] of cors) {
            headers.set(k, v);
        }
        headers.set('Vary', 'Origin, Accept');
        return new Response(cachedRes.body, {
            status: cachedRes.status,
            statusText: cachedRes.statusText,
            headers
        });
    }

    // 2. Cache Miss: Fetch from object storage
    const targetUrl = `${downloadUrl}/file/${BUCKET}${filePath}`;
    const upstreamHeaders = new Headers({ 'Authorization': authToken });

    // Forward Range header if present (important for media streaming/partial content)
    const range = request.headers.get('Range');
    if (range) {
        upstreamHeaders.set('Range', range);
    }

    const method = request.method === 'HEAD' ? 'HEAD' : 'GET';
    const upstreamRes = await fetch(targetUrl, {
        method,
        headers: upstreamHeaders,
        cf: {
            cacheEverything: !isDownload,
            cacheTtl: isDownload ? 0 : 31536000 // Cache static media for 1 year, never cache dynamic downloads
        }
    });

    if (!upstreamRes.ok) {
        return new Response(`Asset not found`, {
            status: upstreamRes.status,
            headers: corsHeaders(originHeader)
        });
    }

    // 3. Prepare response headers
    const outHeaders = new Headers(upstreamRes.headers);
    const cors = corsHeaders(originHeader);
    for (const [k, v] of cors) {
        outHeaders.set(k, v);
    }

    // Attachment Content-Disposition handling for clean downloads
    if (url.searchParams.has('filename')) {
        const rawFn = url.searchParams.get('filename');
        const safeAscii = rawFn.replace(/[^\x20-\x7E]/g, '_').replace(/["\\]/g, '');
        outHeaders.set('Content-Disposition', `attachment; filename="${safeAscii}"; filename*=UTF-8''${encodeURIComponent(rawFn)}`);
    } else if (url.pathname.endsWith('.zip')) {
        const defaultFn = url.pathname.split('/').pop() || 'download.zip';
        outHeaders.set('Content-Disposition', `attachment; filename="${defaultFn}"`);
    }

    // Optimize headers for CDN delivery
    if (isDownload) {
        outHeaders.set('Cache-Control', 'private, no-cache, no-store, must-revalidate');
    } else {
        outHeaders.set('Cache-Control', 'public, max-age=31536000, immutable');
    }
    outHeaders.set('Accept-Ranges', 'bytes');

    // HLS playlists/segments uploaded before content types were set explicitly
    if (filePath.endsWith('.m3u8')) {
        outHeaders.set('Content-Type', 'application/vnd.apple.mpegurl');
    } else if (filePath.endsWith('.ts')) {
        outHeaders.set('Content-Type', 'video/mp2t');
    }
    outHeaders.set('Vary', 'Origin, Accept');

    // Strip internal S3 headers
    const headersToRemove = ['via', 'x-cache', 'x-amz-cf-id', 'x-amz-cf-pop', 'x-amz-request-id', 'x-amz-version-id'];
    headersToRemove.forEach(h => outHeaders.delete(h));
    for (const [key] of upstreamRes.headers) {
        if (key.toLowerCase().startsWith('x-amz-') || key.toLowerCase().startsWith('x-bz-')) {
            outHeaders.delete(key);
        }
    }

    const response = new Response(upstreamRes.body, {
        status: upstreamRes.status,
        headers: outHeaders
    });

    // 4. Save to Cloudflare Edge Cache in background (static media only)
    if (!isDownload && cache && event && event.waitUntil && request.method === 'GET' && upstreamRes.status === 200) {
        event.waitUntil(cache.put(cacheKey, response.clone()));
    }

    return response;
}

function corsHeaders(origin) {
    let allowOrigin = 'https://ifotoset.com';
    if (origin && isTrustedHost(hostOf(origin))) {
        allowOrigin = origin;
    }

    return new Headers({
        'Access-Control-Allow-Origin': allowOrigin,
        'Access-Control-Allow-Methods': 'GET, HEAD, OPTIONS',
        'Access-Control-Allow-Headers': 'Range, Content-Type, Origin, Accept, Authorization',
        'Access-Control-Expose-Headers': 'Accept-Ranges, Content-Range, Content-Length, Content-Type, Cache-Control',
        'Vary': 'Origin'
    });
}

// Rejects traversal and smuggled separators before any auth or storage lookup.
function isSafePath(pathname) {
    if (/%2f|%5c|%00/i.test(pathname)) return false;

    let decoded;
    try {
        decoded = decodeURIComponent(pathname);
    } catch {
        return false;
    }

    if (decoded.includes('\\') || decoded.includes('//')) return false;
    const segments = decoded.split('/');
    return !segments.includes('..') && !segments.includes('.');
}

function isDownloadRequest(url) {
    return url.pathname.includes('/downloads/') || url.pathname.endsWith('.zip') || url.searchParams.has('filename') || url.searchParams.has('download');
}

function hostOf(value) {
    if (!value) return null;
    try {
        return new URL(value.includes('://') ? value : `http://${value}`).hostname.toLowerCase();
    } catch {
        return null;
    }
}

function isTrustedHost(host) {
    if (!host) return false;
    if (host === 'ifotoset.com' || host.endsWith('.ifotoset.com')) return true;
    if (host === 'localhost' || host.endsWith('.localhost')) return true;
    return host === '127.0.0.1' || /^192\.168\.\d{1,3}\.\d{1,3}$/.test(host);
}

function isAllowedReferer(req) {
    // 1. Exact hostname match on Referer or Origin (substring matches are spoofable, e.g. evil.com/?ifotoset.com)
    if (isTrustedHost(hostOf(req.headers.get('Referer'))) || isTrustedHost(hostOf(req.headers.get('Origin')))) {
        return true;
    }

    // 2. Block direct browser navigation (when URL is pasted directly into address bar)
    const secDest = req.headers.get('Sec-Fetch-Dest');
    const secMode = req.headers.get('Sec-Fetch-Mode');
    if (secMode === 'navigate' || secDest === 'document') {
        return false;
    }

    // 3. Allow embedded image, video, and audio elements
    if (secDest === 'image' || secDest === 'video' || secDest === 'audio') {
        return true;
    }

    return false;
}

async function isAllowedRequest(request, url) {
    const exp = url.searchParams.get('exp');
    const sig = url.searchParams.get('sig');
    const objectPath = decodeURIComponent(url.pathname);

    // 1. Protected media and every download (ZIPs, ?filename=, ?download) require a valid, unexpired signed token.
    //    The backend signs all CDN download URLs (StorageService::generatePresignedDownloadUrl).
    if (url.pathname.startsWith('/protected-') || isDownloadRequest(url)) {
        return await verifyMediaToken(objectPath, exp, sig);
    }

    // 2. Public Galleries: Enforce referer check (allow signed token bypass if present)
    if (url.pathname.startsWith('/galleries/')) {
        if (exp && sig && await verifyMediaToken(objectPath, exp, sig)) {
            return true;
        }
        return isAllowedReferer(request);
    }

    // Avatars and public brand assets remain accessible for emails and profiles
    return true;
}

// Directory-scoped token embedded in the path: /{dir}/_t/{exp}/{sig}/{file...}
// Used for HLS so relative playlist/segment references inherit the token (query strings would be dropped).
// The signature covers "{dir}/:{exp}", matching MediaTokenService::signDirectoryUrl().
function extractPathToken(pathname) {
    const match = pathname.match(/^(\/(?:protected-)?galleries\/.+?)\/_t\/(\d+)\/([0-9a-fA-F]{64})\/(.+)$/);
    if (!match) return null;

    const [, dir, exp, sig, rest] = match;
    if (rest.split('/').includes('..')) return null;

    return { dir, exp, sig, objectPath: `${dir}/${rest}` };
}

async function verifyMediaToken(pathname, exp, sig) {
    if (!exp || !sig) return false;

    const nowSec = Math.floor(Date.now() / 1000);
    const expNum = parseInt(exp, 10);
    if (isNaN(expNum) || expNum < nowSec) {
        return false;
    }

    try {
        const cleanPath = pathname.replace(/^\/+/, '');
        const data = new TextEncoder().encode(`${cleanPath}:${exp}`);
        const key = await crypto.subtle.importKey(
            'raw',
            new TextEncoder().encode(CDN_SECRET),
            { name: 'HMAC', hash: 'SHA-256' },
            false,
            ['verify']
        );

        const sigHex = sig.trim().toLowerCase();
        if (sigHex.length !== 64 || !/^[0-9a-f]+$/.test(sigHex)) {
            return false;
        }

        const sigBytes = new Uint8Array(sigHex.match(/.{1,2}/g).map(byte => parseInt(byte, 16)));
        return await crypto.subtle.verify('HMAC', key, sigBytes, data);
    } catch {
        return false;
    }
}


