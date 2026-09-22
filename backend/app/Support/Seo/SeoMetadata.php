<?php

namespace App\Support\Seo;

use App\Models\Gallery;
use App\Models\User;
use App\Services\PublicUrlService;

class SeoMetadata
{
    public function __construct(
        public readonly string $title,
        public readonly string $description,
        public readonly string $canonical,
        public readonly string $robots = 'index, follow, max-image-preview:large',
        public readonly ?string $ogTitle = null,
        public readonly ?string $ogDescription = null,
        public readonly ?string $ogImage = null,
        public readonly string $ogType = 'website',
        public readonly string $twitterCard = 'summary_large_image',
        public readonly array|string|null $structuredData = null,
    ) {}

    public static function make(
        string $title,
        string $description,
        string $canonical,
        string $robots = 'index, follow, max-image-preview:large',
        ?string $ogTitle = null,
        ?string $ogDescription = null,
        ?string $ogImage = null,
        string $ogType = 'website',
        string $twitterCard = 'summary_large_image',
        array|string|null $structuredData = null,
    ): self {
        return new self(
            title: $title,
            description: $description,
            canonical: $canonical,
            robots: $robots,
            ogTitle: $ogTitle ?? $title,
            ogDescription: $ogDescription ?? $description,
            ogImage: $ogImage,
            ogType: $ogType,
            twitterCard: $twitterCard,
            structuredData: $structuredData,
        );
    }

    /**
     * Factory for platform homepage SEO.
     */
    public static function forHome(): self
    {
        $urlService = app(PublicUrlService::class);
        $canonical = $urlService->home();
        $title = 'ifotoset - Professional Photography Client Galleries & Studio Platform';
        $description = 'Create stunning client galleries, deliver private collections, and manage bookings and client proofing. Built for modern photographers in East Africa and worldwide.';

        $structuredData = [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'Organization',
                    '@id' => $canonical . '#organization',
                    'name' => 'ifotoset',
                    'url' => $canonical,
                    'logo' => [
                        '@type' => 'ImageObject',
                        'url' => url('/logo.png'),
                    ],
                ],
                [
                    '@type' => 'WebSite',
                    '@id' => $canonical . '#website',
                    'url' => $canonical,
                    'name' => 'ifotoset',
                    'publisher' => ['@id' => $canonical . '#organization'],
                ],
                [
                    '@type' => 'SoftwareApplication',
                    'name' => 'ifotoset',
                    'applicationCategory' => 'MultimediaApplication',
                    'operatingSystem' => 'Web',
                    'url' => $canonical,
                    'description' => $description,
                ],
            ],
        ];

        return new self(
            title: $title,
            description: $description,
            canonical: $canonical,
            robots: 'index, follow, max-image-preview:large',
            ogTitle: $title,
            ogDescription: $description,
            ogImage: url('/logo.png'),
            ogType: 'website',
            twitterCard: 'summary_large_image',
            structuredData: $structuredData,
        );
    }

    /**
     * Factory for public photographer profile SEO.
     */
    public static function forPhotographer(User $photographer, array $packages = []): self
    {
        $urlService = app(PublicUrlService::class);
        $canonical = $urlService->photographer($photographer);

        $locationText = $photographer->location ? " in {$photographer->location}" : '';
        $title = "{$photographer->name}{$locationText} - Photography Portfolio | ifotoset";
        $description = !empty($photographer->bio)
            ? strip_tags($photographer->bio)
            : "View photography collections, client galleries, and book photo sessions with {$photographer->name}{$locationText}.";

        $robots = $photographer->isPubliclyIndexable()
            ? 'index, follow, max-image-preview:large'
            : 'noindex, nofollow';

        $structuredData = [
            '@context' => 'https://schema.org',
            '@type' => 'ProfilePage',
            'mainEntity' => [
                '@type' => 'Person',
                'name' => $photographer->name,
                'identifier' => $photographer->username,
                'url' => $canonical,
                'image' => $photographer->avatar_url,
                'description' => $photographer->bio,
            ],
        ];

        if ($photographer->location) {
            $structuredData['mainEntity']['homeLocation'] = [
                '@type' => 'Place',
                'name' => $photographer->location,
            ];
        }

        return new self(
            title: $title,
            description: $description,
            canonical: $canonical,
            robots: $robots,
            ogTitle: "{$photographer->name} - Photography Portfolio",
            ogDescription: $description,
            ogImage: $photographer->avatar_url,
            ogType: 'profile',
            twitterCard: 'summary_large_image',
            structuredData: $structuredData,
        );
    }

    /**
     * Factory for public gallery SEO.
     */
    public static function forGallery(Gallery $gallery, ?string $coverUrl = null): self
    {
        $urlService = app(PublicUrlService::class);
        $canonical = $urlService->gallery($gallery);
        $photographer = $gallery->user;
        $photographerName = $photographer?->name ?? 'Photographer';

        // Indexability decision is strictly based on the gallery's privacy/visibility configuration
        $isIndexable = $gallery->isPubliclyIndexable();
        $robots = $isIndexable
            ? 'index, follow, max-image-preview:large'
            : 'noindex, nofollow';

        if ($isIndexable) {
            $title = "{$gallery->title} - {$photographerName} | ifotoset";
            $description = "View photo collection '{$gallery->title}' photographed by {$photographerName}.";
        } else {
            $title = !empty($gallery->password_hash) ? 'PIN Protected Gallery | ifotoset' : 'Private Gallery | ifotoset';
            $description = !empty($gallery->password_hash)
                ? 'This photo gallery is PIN protected.'
                : 'This photo gallery is private and accessible by invitation only.';
        }

        $structuredData = null;
        if ($isIndexable) {
            $structuredData = [
                '@context' => 'https://schema.org',
                '@type' => 'WebPage',
                'name' => $gallery->title,
                'url' => $canonical,
                'description' => $description,
                'author' => [
                    '@type' => 'Person',
                    'name' => $photographerName,
                    'url' => $photographer ? $urlService->photographer($photographer) : null,
                ],
            ];
        }

        return new self(
            title: $title,
            description: $description,
            canonical: $canonical,
            robots: $robots,
            ogTitle: $title,
            ogDescription: $description,
            ogImage: $coverUrl,
            ogType: 'website',
            twitterCard: 'summary_large_image',
            structuredData: $structuredData,
        );
    }

    /**
     * Factory for non-indexable utility/auth pages.
     */
    public static function noIndex(string $title, ?string $description = null, string $robots = 'noindex, follow'): self
    {
        return new self(
            title: $title . ' - ifotoset',
            description: $description ?? 'ifotoset photography platform.',
            canonical: url()->current(),
            robots: $robots,
        );
    }
}
