import { NextResponse } from 'next/server'
import type { NextRequest } from 'next/server'
import { Routes } from './lib/routes'

// Laravel session cookie name = APP_NAME + '_session' (lowercased, snake_cased)
// APP_NAME=ifotoset → cookie name = 'ifotoset_session'
const SESSION_COOKIE = 'ifotoset_session'

export default function proxy(request: NextRequest) {
  const sessionCookie = request.cookies.get(SESSION_COOKIE)
  const path = request.nextUrl.pathname

  const isProtectedRoute = path.startsWith('/studio') || path.startsWith('/admin')

  // Only block access to protected routes when there's NO session cookie at all.
  // We do NOT redirect /login → /dashboard here because the cookie may still
  // exist after logout (the server invalidated the session but the browser
  // keeps the cookie). Redirecting there would cause an infinite loop:
  //   proxy → /studio/dashboard → AuthGuard 401 → /login → proxy → …
  // The login page's useEffect already handles redirecting valid sessions.
  if (isProtectedRoute && !sessionCookie) {
    return NextResponse.redirect(new URL(Routes.login, request.url))
  }

  return NextResponse.next()
}

export const config = {
  matcher: ['/studio/:path*', '/admin/:path*'],
}
