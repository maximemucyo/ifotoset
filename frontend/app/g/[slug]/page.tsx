import { Metadata } from 'next'
import { notFound } from 'next/navigation'
import { headers } from 'next/headers'
import { unstable_noStore as noStore } from 'next/cache'
import { getPublicGallery, getPublicGalleryPhotos } from '@/lib/queries/galleries'
import { PublicGalleryViewClient } from './PublicGalleryViewClient'
import { ApiError } from '@/lib/apiClient'

interface PageProps {
  params: Promise<{ slug: string }>
  searchParams: Promise<{ invite?: string; token?: string }>
}

// Default ISR revalidation for public routes
export const revalidate = 10

export async function generateMetadata({ params, searchParams }: PageProps): Promise<Metadata> {
  const { slug } = await params
  const { invite } = await searchParams
  const baseUrl = process.env.NEXT_PUBLIC_APP_URL || 'https://ifotoset.com'

  try {
    // Attempt to fetch public gallery details
    const res = await getPublicGallery(slug, invite || null, null)
    const gallery = res.data

    // Enforce metadata privacy for password-protected or unlisted galleries
    const isPrivate = gallery.visibility === 'private' || gallery.has_password
    if (isPrivate) {
      return {
        title: 'Private Gallery | ifotoset',
        description: 'This is a private, password-protected photo gallery.',
        robots: {
          index: false,
          follow: false,
        },
      }
    }

    const title = `${gallery.title} ${gallery.client_name ? `for ${gallery.client_name}` : ''} | ifotoset`
    const description = `Browse high-resolution photographs in the gallery "${gallery.title}" on ifotoset.`
    const coverUrl = gallery.cover_photo?.variants?.lg || gallery.cover_photo?.cdn_url || `${baseUrl}/logo-og.png`

    return {
      title,
      description,
      alternates: {
        canonical: `${baseUrl}/g/${slug}`,
      },
      openGraph: {
        title,
        description,
        url: `${baseUrl}/g/${slug}`,
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
    }
  } catch (err) {
    // If not found, Next.js handles 404 gracefully
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
  const { slug } = await params
  const { invite, token } = await searchParams

  try {
    // Fetch gallery details first
    const galleryRes = await getPublicGallery(slug, invite || null, token || null)
    const gallery = galleryRes.data

    const isPrivate = gallery.visibility === 'private' || gallery.has_password

    if (isPrivate) {
      // Opt out of shared cache (ISR) for password protected/restricted paths
      noStore()
      await headers()

      return (
        <PublicGalleryViewClient
          slug={slug}
          inviteToken={invite || null}
          initialGallery={null} // Force client component to query with client session token
          initialPhotos={[]}
          initialNextCursor={null}
          initialHasMore={true}
          initialError={{
            code: gallery.has_password ? 'PASSWORD_REQUIRED' : 'INVITATION_REQUIRED',
            message: gallery.has_password ? 'This gallery is password-protected.' : 'Invitation required.',
            requiresPassword: gallery.has_password,
            requiresInvitation: !gallery.has_password,
            httpStatus: 403,
          }}
        />
      )
    }

    // For public unprotected galleries, pre-fetch first page of photos on the server
    const photosRes = await getPublicGalleryPhotos(slug, null, 60, invite || null, token || null)

    const jsonLd = {
      '@context': 'https://schema.org',
      '@type': 'ImageGallery',
      'name': gallery.title,
      'description': `Photo collection: ${gallery.title}`,
      'creator': gallery.client_name ? {
        '@type': 'Person',
        'name': gallery.client_name,
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

    // Render client components with error states directly
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
        initialHasMore={true}
        initialError={errorDetails}
      />
    )
  }
}
