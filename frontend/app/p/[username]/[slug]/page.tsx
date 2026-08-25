import { Metadata } from 'next'
import { notFound, permanentRedirect } from 'next/navigation'
import { getPublicGallery, getPublicGalleryPhotos } from '@/lib/queries/galleries'
import { PublicGalleryViewClient } from './PublicGalleryViewClient'
import { ApiError } from '@/lib/apiClient'

interface PageProps {
  params: Promise<{ username: string; slug: string }>
  searchParams: Promise<{ invite?: string; token?: string }>
}

// Disable static generation since ports/tenants resolve dynamically
export const dynamic = 'force-dynamic'

export async function generateMetadata({ params, searchParams }: PageProps): Promise<Metadata> {
  const { username, slug } = await params
  const { invite } = await searchParams
  const rootDomain = process.env.NEXT_PUBLIC_ROOT_DOMAIN || 'localhost:3000'
  const protocol = process.env.NEXT_PUBLIC_PROTOCOL || 'http'

  try {
    const res = await getPublicGallery(slug, invite || null, null, username)
    const gallery = res.data

    const photographerName = gallery.photographer?.name || 'Photographer'
    const title = `${gallery.title} by ${photographerName} | ifotoset`
    const description = `Browse high-resolution photographs in the gallery "${gallery.title}" on ifotoset.`
    const coverUrl = gallery.cover_photo?.variants?.lg || gallery.cover_photo?.cdn_url || `${protocol}://www.${rootDomain}/logo-og.png`

    const canonicalUrl = `${protocol}://${gallery.photographer?.username || username}.${rootDomain}/${slug}`

    const isIndexed = gallery.visibility === 'public' && gallery.access_granted && !gallery.requires_password && !gallery.requires_invitation

    return {
      title,
      description,
      alternates: {
        canonical: canonicalUrl,
      },
      openGraph: {
        title,
        description,
        url: canonicalUrl,
        images: [
          {
            url: coverUrl,
            alt: gallery.title,
          },
        ],
        type: 'website',
      },
      twitter: {
        card: 'summary_large_image',
        title,
        description,
        images: [coverUrl],
      },
      robots: isIndexed ? { index: true, follow: true } : { index: false, follow: false },
    }
  } catch (err) {
    return {
      title: 'Gallery Not Found | ifotoset',
      description: 'The requested photo gallery was not found.',
      robots: {
        index: false,
        follow: false,
      },
    }
  }
}

export default async function PublicGalleryView({ params, searchParams }: PageProps) {
  const { username, slug } = await params
  const { invite, token } = await searchParams
  const rootDomain = process.env.NEXT_PUBLIC_ROOT_DOMAIN || 'localhost:3000'
  const protocol = process.env.NEXT_PUBLIC_PROTOCOL || 'http'

  try {
    // Fetch gallery details first
    const galleryRes = await getPublicGallery(slug, invite || null, token || null, username)
    const gallery = galleryRes.data

    // Validate photographer username match
    const correctUsername = gallery.photographer?.username
    if (correctUsername && correctUsername.toLowerCase() !== username.toLowerCase()) {
      const query = new URLSearchParams()
      if (invite) query.append('invite', invite)
      if (token) query.append('token', token)
      const queryStr = query.toString()
      permanentRedirect(`${protocol}://${correctUsername.toLowerCase()}.${rootDomain}/${slug}${queryStr ? `?${queryStr}` : ''}`)
    }

    // Check if access is granted (public vs private/password protected)
    if (!gallery.access_granted) {
      return (
        <PublicGalleryViewClient
          slug={slug}
          inviteToken={invite || null}
          initialGallery={gallery}
          initialPhotos={[]}
          initialNextCursor={null}
          initialHasMore={false}
          initialError={{
            code: gallery.error_code || 'PASSWORD_REQUIRED',
            message: gallery.error_message || 'This gallery is password-protected.',
            requiresPassword: gallery.requires_password || false,
            requiresInvitation: gallery.requires_invitation || false,
            httpStatus: 403,
          }}
        />
      )
    }

    // Eager load first page of photos if access is fully granted
    const photosRes = await getPublicGalleryPhotos(slug, null, 60, invite || null, token || null, username)

    const jsonLd = {
      '@context': 'https://schema.org',
      '@type': 'ImageGallery',
      'name': gallery.title,
      'description': `Photo collection: ${gallery.title}`,
      'creator': gallery.photographer ? {
        '@type': 'Person',
        'name': gallery.photographer.name,
      } : undefined,
      'dateCreated': gallery.created_at,
    }

    return (
      <>
        <script
          type="application/ld+json"
          dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd) }}
        />
        <PublicGalleryViewClient
          slug={slug}
          inviteToken={invite || null}
          initialGallery={gallery}
          initialPhotos={photosRes.data}
          initialNextCursor={photosRes.next_cursor}
          initialHasMore={photosRes.has_more}
          initialError={null}
        />
      </>
    )
  } catch (err) {
    if (err instanceof ApiError && err.status === 404) {
      notFound()
    }

    const errorDetails = err instanceof ApiError ? {
      code: err.code,
      message: err.message,
      httpStatus: err.status,
      requiresPassword: err.code === 'PASSWORD_REQUIRED',
      requiresInvitation: err.code === 'INVITATION_REQUIRED',
    } : {
      code: 'FETCH_ERROR',
      message: err instanceof Error ? err.message : 'Failed to load gallery.',
    }

    return (
      <PublicGalleryViewClient
        slug={slug}
        inviteToken={invite || null}
        initialGallery={null}
        initialPhotos={[]}
        initialNextCursor={null}
        initialHasMore={false}
        initialError={errorDetails}
      />
    )
  }
}
