import type { MetadataRoute } from 'next'

interface SitemapItem {
  username?: string
  slug?: string
  lastModified: string
}

interface SitemapApiResponse {
  photographers: SitemapItem[]
  galleries: SitemapItem[]
}

export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
  const baseUrl = process.env.NEXT_PUBLIC_APP_URL || 'https://ifotoset.com'
  const apiUrl = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000'

  const routes: MetadataRoute.Sitemap = [
    {
      url: baseUrl,
      lastModified: new Date().toISOString(),
      changeFrequency: 'daily',
      priority: 1.0,
    },
  ]

  try {
    const res = await fetch(`${apiUrl}/api/v1/public/sitemap`, {
      next: { revalidate: 3600 }, // Cache sitemap for 1 hour
    })

    if (res.ok) {
      const data: SitemapApiResponse = await res.json()

      // Add photographers
      if (data.photographers && Array.isArray(data.photographers)) {
        data.photographers.forEach((p) => {
          if (p.username) {
            routes.push({
              url: `${baseUrl}/p/${p.username}`,
              lastModified: p.lastModified,
              changeFrequency: 'weekly',
              priority: 0.8,
            })
          }
        })
      }

      // Add galleries
      if (data.galleries && Array.isArray(data.galleries)) {
        data.galleries.forEach((g) => {
          if (g.slug) {
            routes.push({
              url: `${baseUrl}/g/${g.slug}`,
              lastModified: g.lastModified,
              changeFrequency: 'weekly',
              priority: 0.6,
            })
          }
        })
      }
    }
  } catch (err) {
    console.error('Failed to fetch sitemap data', err)
  }

  return routes
}
