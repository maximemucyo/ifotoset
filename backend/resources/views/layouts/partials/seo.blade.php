@php
    /** @var \App\Support\Seo\SeoMetadata|null $seo */
    $title = $seo?->title ?? ($title ?? config('app.name', 'ifotoset'));
    $description = $seo?->description ?? ($description ?? 'Professional photography proofing, client galleries, and booking platform.');
    $canonical = $seo?->canonical ?? ($canonicalUrl ?? url()->current());
    $robots = $seo?->robots ?? ($robots ?? 'index, follow, max-image-preview:large');
    $ogTitle = $seo?->ogTitle ?? $title;
    $ogDescription = $seo?->ogDescription ?? $description;
    $ogImage = $seo?->ogImage ?? ($ogImage ?? null) ?? \App\Support\Seo\SeoMetadata::defaultImage();
    $ogImageIsDefault = $ogImage === \App\Support\Seo\SeoMetadata::defaultImage();
    $ogType = $seo?->ogType ?? 'website';
    $twitterCard = $seo?->twitterCard ?? 'summary_large_image';
    $structuredData = $seo?->structuredData ?? ($structuredData ?? null);
@endphp

<title>{{ $title }}</title>
<meta name="description" content="{{ $description }}">
<link rel="canonical" href="{{ $canonical }}">
<meta name="robots" content="{{ $robots }}">

<!-- OpenGraph / Social -->
<meta property="og:site_name" content="ifotoset">
<meta property="og:title" content="{{ $ogTitle }}">
<meta property="og:description" content="{{ $ogDescription }}">
<meta property="og:url" content="{{ $canonical }}">
<meta property="og:type" content="{{ $ogType }}">
<meta property="og:image" content="{{ $ogImage }}">
<meta property="og:image:alt" content="{{ $ogTitle }}">
@if($ogImageIsDefault)
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
@endif

<!-- Twitter Cards -->
<meta name="twitter:card" content="{{ $twitterCard }}">
<meta name="twitter:title" content="{{ $ogTitle }}">
<meta name="twitter:description" content="{{ $ogDescription }}">
<meta name="twitter:image" content="{{ $ogImage }}">

@if($structuredData)
<script type="application/ld+json">
{!! is_array($structuredData) ? json_encode($structuredData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : $structuredData !!}
</script>
@endif
