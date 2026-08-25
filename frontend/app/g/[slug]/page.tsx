import { notFound, permanentRedirect } from 'next/navigation'
import { getPublicGallery } from '@/lib/queries/galleries'
import { ApiError } from '@/lib/apiClient'

interface RedirectPageProps {
  params: Promise<{ slug: string }>
  searchParams: Promise<{ invite?: string; token?: string }>
}

// Disable static generation since redirects resolve dynamically
export const dynamic = 'force-dynamic'

export default async function PublicGalleryRedirect({ params, searchParams }: RedirectPageProps) {
  const { slug } = await params
  const { invite, token } = await searchParams

  let username: string | null = null

  try {
    // Attempt to fetch public gallery details to get the photographer's username
    const res = await getPublicGallery(slug, invite || null, token || null)
    username = res.data.photographer?.username || null
  } catch (err) {
    // If not found, Next.js handles 404 gracefully
    if (err instanceof ApiError && err.status === 404) {
      notFound()
    }
  }

  if (username) {
    const rootDomain = process.env.NEXT_PUBLIC_ROOT_DOMAIN || 'localhost:3000'
    const protocol = process.env.NEXT_PUBLIC_PROTOCOL || 'http'
    const targetSubdomain = username.toLowerCase()

    const query = new URLSearchParams()
    if (invite) query.append('invite', invite)
    if (token) query.append('token', token)
    const queryStr = query.toString()

    permanentRedirect(`${protocol}://${targetSubdomain}.${rootDomain}/${slug}${queryStr ? `?${queryStr}` : ''}`)
  }

  notFound()
}
