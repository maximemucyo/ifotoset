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
                        'url' => url('/icon-512.png'),
                    ],
                ],
                [
                    '@type' => 'WebSite',
                    '@id' => $canonical . '#website',
                    'url' => $canonical,
                    'name' => 'ifotoset',
                    'publisher' => ['@id' => $canonical . '#organization'],
                ],
                array_filter([
                    '@type' => 'SoftwareApplication',
                    'name' => 'ifotoset',
                    'applicationCategory' => 'MultimediaApplication',
                    'operatingSystem' => 'Web',
                    'url' => $canonical,
                    'description' => $description,
                    'offers' => self::planOffers(),
                ]),
            ],
        ];

        return new self(
            title: $title,
            description: $description,
            canonical: $canonical,
            robots: 'index, follow, max-image-preview:large',
            ogTitle: $title,
            ogDescription: $description,
            ogImage: self::defaultImage(),
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
            ? \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags($photographer->bio))), 155)
            : "View photography collections, client galleries, and book photo sessions with {$photographer->name}{$locationText}.";

        $robots = $photographer->isPubliclyIndexable()
            ? 'index, follow, max-image-preview:large'
            : 'noindex, nofollow';

        $person = array_filter([
            '@type' => 'Person',
            '@id' => $canonical . '#person',
            'name' => $photographer->name,
            'identifier' => $photographer->username,
            'url' => $canonical,
            'image' => $photographer->avatar_url,
            'description' => $photographer->bio ? strip_tags($photographer->bio) : null,
            'homeLocation' => $photographer->location ? ['@type' => 'Place', 'name' => $photographer->location] : null,
            'sameAs' => $photographer->website ? [$photographer->website] : null,
        ]);

        $offers = collect($packages)->map(fn ($package) => array_filter([
            '@type' => 'Offer',
            'name' => $package->name,
            'description' => $package->description ? strip_tags($package->description) : null,
            'price' => $package->price !== null ? (string) $package->price : null,
            'priceCurrency' => $package->currency,
        ]))->values()->all();

        $service = array_filter([
            '@type' => 'ProfessionalService',
            '@id' => $canonical . '#service',
            'name' => "{$photographer->name} Photography",
            'url' => $canonical,
            'image' => $photographer->avatar_url,
            'founder' => ['@id' => $canonical . '#person'],
            'areaServed' => $photographer->location ?: null,
            'makesOffer' => $offers ?: null,
        ]);

        $structuredData = [
            '@context' => 'https://schema.org',
            '@graph' => [
                ['@type' => 'ProfilePage', 'url' => $canonical, 'mainEntity' => ['@id' => $canonical . '#person']],
                $person,
                $service,
            ],
        ];

        return new self(
            title: $title,
            description: $description,
            canonical: $canonical,
            robots: $robots,
            ogTitle: "{$photographer->name} - Photography Portfolio",
            ogDescription: $description,
            ogImage: $photographer->avatar_url ?: self::defaultImage(),
            ogType: 'profile',
            twitterCard: 'summary_large_image',
            structuredData: $structuredData,
        );
    }

    /**
     * Factory for public gallery SEO.
     */
    public static function forGallery(Gallery $gallery, ?string $coverUrl = null, bool $accessGranted = false): self
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

        // Visitors who unlocked a PIN/private gallery see its real title; it stays noindex, and link-preview
        // crawlers never unlock it, so shared previews keep the generic wording.
        if ($isIndexable || $accessGranted) {
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
                '@type' => 'ImageGallery',
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
            ogImage: $isIndexable && $coverUrl ? $coverUrl : self::defaultImage(),
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

    /**
     * 1200x630 brand image used whenever a page has no image of its own.
     */
    public static function defaultImage(): string
    {
        return asset('og-default.jpg');
    }

    /**
     * Paid plans as schema.org offers (monthly price).
     */
    private static function planOffers(): ?array
    {
        try {
            $offers = \App\Models\Plan::query()
                ->where('monthly_price', '>', 0)
                ->orderBy('monthly_price')
                ->get(['name', 'monthly_price', 'currency'])
                ->map(fn ($plan) => [
                    '@type' => 'Offer',
                    'name' => $plan->name,
                    'price' => (string) $plan->monthly_price,
                    'priceCurrency' => $plan->currency ?: 'RWF',
                ])
                ->values()
                ->all();
        } catch (\Throwable $e) {
            return null;
        }

        return $offers ?: null;
    }
}
