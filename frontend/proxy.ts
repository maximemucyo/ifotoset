import { NextResponse } from 'next/server'
import type { NextRequest } from 'next/server'
import { Routes } from './lib/routes'

// Reserved/system subdomains that should not be resolved as photographer tenants
const RESERVED_SUBDOMAINS = [
  'root', 'www', 'api', 'admin', 'studio', 'app', 'support', 'blog', 'dev', 'status', 'assets',
  'dashboard', 'auth', 'login', 'signup', 'register', 'help', 'mail', 'email', 'cdn',
  'static', 'docs', 'billing', 'account', 'settings', 'security', 'localhost', 'test',
  'staging', 'pricing', 'smtp', 'ftp', 'ns1', 'ns2'
]

const SESSION_COOKIE = 'ifotoset_session'

export default function proxy(request: NextRequest) {
  const url = request.nextUrl
  const hostname = request.headers.get('host') || ''

  const rootDomain = process.env.NEXT_PUBLIC_ROOT_DOMAIN || 'localhost:3000'
  const protocol = process.env.NEXT_PUBLIC_PROTOCOL || 'http'

  // Normalize host: strip port and make lowercase
  const host = hostname.split(':')[0].toLowerCase()
  const rootHost = rootDomain.split(':')[0].toLowerCase()

  // Helper to extract tenant subdomain
  const getTenantFromHost = (h: string): string | null => {
    // Exact match for main/apex domain
    if (h === rootHost) {
      return null
    }
    // Subdomain suffix check
    if (h.endsWith(`.${rootHost}`)) {
      const subdomain = h.slice(0, h.indexOf(`.${rootHost}`))
      // Safety: Reject multi-label subdomains (e.g. foo.bar.localhost)
      if (subdomain.includes('.')) {
        return null
      }
      if (RESERVED_SUBDOMAINS.includes(subdomain)) {
        return null
      }
      return subdomain
    }
    return null
  }

  const tenant = getTenantFromHost(host)
  const pathname = url.pathname

  // 1. Exclude Next.js internals, APIs, sitemaps, robots.txt, and static assets from rewriting
  if (
    pathname.startsWith('/_next') ||
    pathname.startsWith('/api') ||
    pathname.includes('.') ||
    pathname === '/favicon.ico' ||
    pathname === '/robots.txt' ||
    pathname === '/sitemap.xml' ||
    pathname === '/sitemap.txt'
  ) {
    return NextResponse.next()
  }

  // 2. Auth Protection (merged from legacy proxy.ts)
  const sessionCookie = request.cookies.get(SESSION_COOKIE)
  const isProtectedRoute = pathname.startsWith('/studio') || pathname.startsWith('/admin')
  if (isProtectedRoute && !sessionCookie) {
    return NextResponse.redirect(new URL(Routes.login, request.url))
  }

  // 3. Trailing slash normalization: ONLY for public page routes
  if (pathname !== '/' && pathname.endsWith('/')) {
    const cleanPath = pathname.slice(0, -1)
    const redirectUrl = new URL(request.url)
    redirectUrl.pathname = cleanPath
    return NextResponse.redirect(redirectUrl, 308)
  }

  // 4. Main Domain Redirects: ifotoset.com/p/username/... -> username.ifotoset.com/...
  if (!tenant && pathname.startsWith('/p/')) {
    const parts = pathname.split('/')
    // parts[0] is "", parts[1] is "p", parts[2] is username, parts[3] is slug
    const username = parts[2]
    const slug = parts[3] || ''

    if (username) {
      const targetSubdomain = username.toLowerCase()
      if (!RESERVED_SUBDOMAINS.includes(targetSubdomain)) {
        const query = url.search
        const newPath = slug ? `/${slug}` : '/'
        return NextResponse.redirect(
          `${protocol}://${targetSubdomain}.${rootDomain}${newPath}${query}`,
          308
        )
      }
    }
  }

  // 5. Subdomain Rewrites (Tenant URL dynamic routing)
  if (tenant) {
    // Rewrite path internally:
    // / -> /p/[username]
    // /[slug] -> /p/[username]/[slug]
    // /[slug]/export -> /p/[username]/[slug]/export
    //
    // IMPORTANT: Build the rewrite URL explicitly from `protocol` (NEXT_PUBLIC_PROTOCOL env var)
    // and `hostname` (Host request header = 'maxime1.ifotoset.com'), NOT from `request.url` or
    // `request.nextUrl` — both resolve to the internal Next.js server URL (https://localhost:3004/)
    // behind Nginx, causing 500 errors when Nginx tries to proxy to a localhost HTTPS endpoint.
    const newPath = pathname === '/' ? `/p/${tenant}` : `/p/${tenant}${pathname}`
    const rewriteUrl = new URL(`${protocol}://${hostname}${newPath}${url.search}`)
    return NextResponse.rewrite(rewriteUrl)
  }

  return NextResponse.next()
}

export const config = {
  matcher: [
    /*
     * Match all paths except for:
     * 1. /api routes
     * 2. /_next (Next.js internals)
     * 3. /_static (inside /public)
     * 4. all static files (e.g. favicon.ico, images)
     */
    '/((?!api|_next|_static|_image|favicon.ico|[\\w-]+\\.\\w+).*)',
  ],
}
