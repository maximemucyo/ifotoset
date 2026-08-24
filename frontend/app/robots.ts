import type { MetadataRoute } from 'next'

export default function robots(): MetadataRoute.Robots {
  const baseUrl = process.env.NEXT_PUBLIC_APP_URL || 'https://ifotoset.com'

  return {
    rules: {
      userAgent: '*',
      allow: ['/', '/g/', '/p/'],
      disallow: [
        '/studio/',
        '/admin/',
        '/login',
        '/signup',
        '/forgot-password',
        '/reset-password',
        '/google-photos/',
      ],
    },
    sitemap: `${baseUrl}/sitemap.xml`,
  }
}
