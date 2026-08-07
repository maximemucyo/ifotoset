export const Routes = {
  login: '/login',
  signup: '/signup',
  studioDashboard: '/studio/dashboard',
  studioGalleries: '/studio/galleries',
  adminDashboard: '/admin/dashboard',
  publicProfile: (username: string) => `/p/${username}`,
}
