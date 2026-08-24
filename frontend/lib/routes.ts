export const Routes = {
  login: '/login',
  signup: '/signup',
  forgotPassword: '/forgot-password',
  resetPassword: '/reset-password',
  studioDashboard: '/studio/dashboard',
  studioGalleries: '/studio/galleries',
  adminDashboard: '/admin/dashboard',
  publicProfile: (username: string) => `/p/${username}`,
}
