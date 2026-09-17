// === Cloudflare Worker: cdn.ifotoset.com proxy for object storage ===

const KEY_ID = '003ff5db745bcb20000000001';
const APP_KEY = 'K003I1TxpXT7Q5JA3EMolBQ21T8DbbM';
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

    // Referer & Origin check: block direct URL navigation and hotlinking for gallery assets
    const url = new URL(request.url);
    if (!isAllowedRequest(request, url)) {
        return new Response('Forbidden: direct access is not allowed', {
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
        return await proxyAndCache(request, event);
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

async function proxyAndCache(request, event) {
    const url = new URL(request.url);
    const originHeader = request.headers.get('Origin');

    // Normalize path (remove double slashes, etc.)
    let filePath = decodeURIComponent(url.pathname).replace(/\/{2,}/g, '/');

    // Fallback: If requesting a gallery photo folder without a file extension, default to original.jpg
    const pathParts = filePath.split('/');
    const lastPart = pathParts[pathParts.length - 1];
    if (filePath.startsWith('/galleries/') && !lastPart.includes('.')) {
        filePath = `${filePath}/original.jpg`;
    }

    // Ignore any query params for the cache key to maximize hits
    const cacheKeyUrl = new URL(request.url);
    cacheKeyUrl.search = '';
    const cacheKey = new Request(cacheKeyUrl.toString(), request);

    const cache = caches.default;

    // 1. Check Cloudflare Edge Cache
    let cachedRes = await cache.match(cacheKey);
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
            cacheEverything: true,
            cacheTtl: 31536000 // Instruct Cloudflare to cache for 1 year
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

    // Optimize headers for CDN delivery
    outHeaders.set('Cache-Control', 'public, max-age=31536000, immutable');
    outHeaders.set('Accept-Ranges', 'bytes');
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

    // 4. Save to Cloudflare Edge Cache in background
    if (event && event.waitUntil && request.method === 'GET') {
        event.waitUntil(cache.put(cacheKey, response.clone()));
    }

    return response;
}

function corsHeaders(origin) {
    let allowOrigin = 'https://ifotoset.com';
    if (origin) {
        const o = origin.toLowerCase();
        if (o.includes('ifotoset.com') || o.includes('localhost') || o.includes('127.0.0.1') || o.includes('192.168.')) {
            allowOrigin = origin;
        }
    }

    return new Headers({
        'Access-Control-Allow-Origin': allowOrigin,
        'Access-Control-Allow-Methods': 'GET, HEAD, OPTIONS',
        'Access-Control-Allow-Headers': 'Range, Content-Type, Origin, Accept, Authorization',
        'Access-Control-Expose-Headers': 'Accept-Ranges, Content-Range, Content-Length, Content-Type, Cache-Control',
        'Vary': 'Origin'
    });
}

// Function to check if the referer is allowed (matching agasoba-media-proxy pattern)
function getRefererHost(req) {
    const r = req.headers.get('Referer');
    if (!r) return null;
    try {
        return new URL(r.includes('://') ? r : `http://${r}`).hostname.toLowerCase();
    } catch {
        return null;
    }
}

function isAllowedReferer(req) {
    const r = (req.headers.get('Referer') || '').toLowerCase();
    const o = (req.headers.get('Origin') || '').toLowerCase();

    // 1. String inclusion for ifotoset domains & local environments
    if (r.includes('ifotoset.com') || o.includes('ifotoset.com')) return true;
    if (r.includes('localhost') || o.includes('localhost')) return true;
    if (r.includes('127.0.0.1') || o.includes('127.0.0.1')) return true;
    if (r.includes('192.168.') || o.includes('192.168.')) return true;

    // 2. Structured hostname check fallback
    const host = getRefererHost(req);
    if (host) {
        if (host === 'ifotoset.com' || host.endsWith('.ifotoset.com')) return true;
        if (host === 'localhost' || host.endsWith('.localhost')) return true;
        if (host === '127.0.0.1' || host.startsWith('192.168.')) return true;
    }

    // 3. Block direct browser navigation (when URL is pasted directly into address bar)
    const secDest = req.headers.get('Sec-Fetch-Dest');
    const secMode = req.headers.get('Sec-Fetch-Mode');
    if (secMode === 'navigate' || secDest === 'document') {
        return false;
    }

    // 4. Allow embedded image elements (<img> tag)
    if (secDest === 'image') {
        return true;
    }

    return false;
}

function isAllowedRequest(request, url) {
    // Only enforce referer check on gallery media
    // Avatars and public brand assets remain accessible for emails and profiles
    if (!url.pathname.startsWith('/galleries/')) {
        return true;
    }

    return isAllowedReferer(request);
}


