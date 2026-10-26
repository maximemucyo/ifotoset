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
     * Factory for platform marketing pages (features, pricing) with breadcrumb structured data.
     */
    public static function forMarketingPage(string $title, string $description, string $routeName, string $name): self
    {
        $home = app(PublicUrlService::class)->home();
        $canonical = route($routeName);

        $structuredData = [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'WebPage',
                    '@id' => $canonical . '#webpage',
                    'url' => $canonical,
                    'name' => $title,
                    'description' => $description,
                    'isPartOf' => ['@id' => $home . '#website'],
                    'breadcrumb' => ['@id' => $canonical . '#breadcrumb'],
                ],
                [
                    '@type' => 'BreadcrumbList',
                    '@id' => $canonical . '#breadcrumb',
                    'itemListElement' => [
                        ['@type' => 'ListItem', 'position' => 1, 'name' => 'ifotoset', 'item' => $home],
                        ['@type' => 'ListItem', 'position' => 2, 'name' => $name, 'item' => $canonical],
                    ],
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

        $portfolio = $photographer->portfolio();
        $locationText = $photographer->location ? " in {$photographer->location}" : '';
        $title = $portfolio->seoTitle() ?? "{$photographer->name}{$locationText} - Photography Portfolio | ifotoset";
        $description = $portfolio->seoDescription() ?? (!empty($photographer->bio)
            ? \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags($photographer->bio))), 155)
            : "View photography collections, client galleries, and book photo sessions with {$photographer->name}{$locationText}.");
        $sameAs = array_values(array_filter(array_merge(
            [$photographer->website],
            array_column($portfolio->socialLinks(), 'url'),
        )));

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
            'jobTitle' => $portfolio->roleLabel(),
            'description' => $photographer->bio ? strip_tags($photographer->bio) : null,
            'homeLocation' => $photographer->location ? ['@type' => 'Place', 'name' => $photographer->location] : null,
            'knowsAbout' => $photographer->specialtyLabels() ?: null,
            'sameAs' => $sameAs ?: null,
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
            'image' => $photographer->logo_url ?: $photographer->avatar_url,
            'founder' => ['@id' => $canonical . '#person'],
            'areaServed' => $photographer->location ?: $photographer->cityName(),
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
            ogTitle: $portfolio->seoTitle() ?? "{$photographer->name} - Photography Portfolio",
            ogDescription: $description,
            ogImage: self::photographerImage($photographer),
            ogType: 'profile',
            twitterCard: 'summary_large_image',
            structuredData: $structuredData,
        );
    }

    /**
     * Factory for the portfolio's About and Contact pages.
     */
    public static function forPortfolioPage(User $photographer, string $page): self
    {
        $portfolio = $photographer->portfolio();
        $canonical = app(PublicUrlService::class)->photographer($photographer) . '/' . $page;
        $robots = $photographer->isPubliclyIndexable()
            ? 'index, follow, max-image-preview:large'
            : 'noindex, nofollow';

        if ($page === 'about') {
            $title = "About {$photographer->name} | ifotoset";
            $source = $portfolio->aboutBody() ?? $photographer->bio;
            $description = $source
                ? \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags($source))), 155)
                : "Meet {$photographer->name}, " . strtolower($portfolio->roleLabel()) . ($photographer->location ? " in {$photographer->location}" : '') . '.';
        } else {
            $title = "Contact {$photographer->name} | ifotoset";
            $description = "Get in touch with {$photographer->name} about a photo session" . ($photographer->location ? " in {$photographer->location}" : '') . '.';
        }

        return new self(
            title: $title,
            description: $description,
            canonical: $canonical,
            robots: $robots,
            ogTitle: $title,
            ogDescription: $description,
            ogImage: self::photographerImage($photographer),
            ogType: 'profile',
            twitterCard: 'summary_large_image',
        );
    }

    /**
     * Link-preview image: the dedicated share image, then the cover, then the profile photo.
     */
    protected static function photographerImage(User $photographer): string
    {
        return $photographer->share_image_url
            ?: $photographer->cover_url
            ?: $photographer->avatar_url
            ?: self::defaultImage();
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
