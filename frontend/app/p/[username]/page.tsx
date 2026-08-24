import { Metadata } from 'next'
import { notFound } from 'next/navigation'
import { getPhotographerProfile, getPhotographerReviews } from '@/lib/queries/public'
import { PhotographerLandingPageClient } from './PhotographerLandingPageClient'
import { ApiError } from '@/lib/apiClient'

interface PageProps {
  params: Promise<{ username: string }>
}

export const revalidate = 60 // Revalidate every minute (ISR)

export async function generateMetadata({ params }: PageProps): Promise<Metadata> {
  const { username } = await params
  const baseUrl = process.env.NEXT_PUBLIC_APP_URL || 'https://ifotoset.com'

  try {
    const data = await getPhotographerProfile(username)
    const { photographer } = data
    const title = `${photographer.name} | Photographer in ${photographer.location || 'East Africa'} | ifotoset`
    const description = photographer.bio || `Check out the professional photography portfolio and client galleries of ${photographer.name} on ifotoset.`
    const avatar = photographer.avatar_url || `${baseUrl}/logo-og.png`

    return {
      title,
      description,
      alternates: {
        canonical: `${baseUrl}/p/${username}`,
      },
      openGraph: {
        title,
        description,
        url: `${baseUrl}/p/${username}`,
        images: [
          {
            url: avatar,
            alt: photographer.name,
          },
        ],
        type: 'profile',
      },
      twitter: {
        card: 'summary',
        title,
        description,
        images: [avatar],
      },
    }
  } catch (err) {
    return {
      title: 'Photographer Profile | ifotoset',
      description: 'View professional photography portfolio on ifotoset.',
      robots: { index: false },
    }
  }
}

export default async function PhotographerLandingPage({ params }: PageProps) {
  const { username } = await params

  try {
    const data = await getPhotographerProfile(username)
    const reviews = await getPhotographerReviews(username)

    const { photographer } = data
    const avatarUrl = photographer.avatar_url

    const jsonLd = {
      '@context': 'https://schema.org',
      '@type': 'Person',
      'name': photographer.name,
      'description': photographer.bio || undefined,
      'image': avatarUrl || undefined,
      'jobTitle': 'Photographer',
      'workLocation': photographer.location ? {
        '@type': 'Place',
        'name': photographer.location,
      } : undefined,
      'url': photographer.website || undefined,
      'telephone': photographer.phone || undefined,
    }

    return (
      <>
        <script
          type="application/ld+json"
          dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd) }}
        />
        <PhotographerLandingPageClient
          username={username}
          initialData={data}
          initialReviews={reviews}
        />
      </>
    )
  } catch (err) {
    if (err instanceof ApiError && err.status === 404) {
      notFound()
    }
    throw err
  }
}
