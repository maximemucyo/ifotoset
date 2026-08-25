export const Routes = {
  login: '/login',
  signup: '/signup',
  forgotPassword: '/forgot-password',
  resetPassword: '/reset-password',
  studioDashboard: '/studio/dashboard',
  studioGalleries: '/studio/galleries',
  adminDashboard: '/admin/dashboard',
  publicProfile: (username: string) => `/p/${username}`,
  photographerUrl: (username: string) => {
    const rootDomain = process.env.NEXT_PUBLIC_ROOT_DOMAIN || 'localhost:3000'
    const protocol = process.env.NEXT_PUBLIC_PROTOCOL || 'http'
    const encodedUsername = encodeURIComponent(username.toLowerCase())
    return `${protocol}://${encodedUsername}.${rootDomain}`
  },
  publicGalleryUrl: (username: string, slug: string) => {
    const rootDomain = process.env.NEXT_PUBLIC_ROOT_DOMAIN || 'localhost:3000'
    const protocol = process.env.NEXT_PUBLIC_PROTOCOL || 'http'
    const encodedUsername = encodeURIComponent(username.toLowerCase())
    const encodedSlug = encodeURIComponent(slug)
    return `${protocol}://${encodedUsername}.${rootDomain}/${encodedSlug}`
  },
  galleryExportUrl: (username: string, slug: string) => {
    const rootDomain = process.env.NEXT_PUBLIC_ROOT_DOMAIN || 'localhost:3000'
    const protocol = process.env.NEXT_PUBLIC_PROTOCOL || 'http'
    const encodedUsername = encodeURIComponent(username.toLowerCase())
    const encodedSlug = encodeURIComponent(slug)
    return `${protocol}://${encodedUsername}.${rootDomain}/${encodedSlug}/export`
  },
}
