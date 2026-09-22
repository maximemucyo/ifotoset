@php
    /** @var \App\Support\Seo\SeoMetadata|null $seo */
    $title = $seo?->title ?? ($title ?? config('app.name', 'ifotoset'));
    $description = $seo?->description ?? ($description ?? 'Professional photography proofing, client galleries, and booking platform.');
    $canonical = $seo?->canonical ?? ($canonicalUrl ?? url()->current());
    $robots = $seo?->robots ?? ($robots ?? 'index, follow, max-image-preview:large');
    $ogTitle = $seo?->ogTitle ?? $title;
    $ogDescription = $seo?->ogDescription ?? $description;
    $ogImage = $seo?->ogImage ?? ($ogImage ?? null);
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
@if($ogImage)
<meta property="og:image" content="{{ $ogImage }}">
@endif

<!-- Twitter Cards -->
<meta name="twitter:card" content="{{ $twitterCard }}">
<meta name="twitter:title" content="{{ $ogTitle }}">
<meta name="twitter:description" content="{{ $ogDescription }}">
@if($ogImage)
<meta name="twitter:image" content="{{ $ogImage }}">
@endif

@if($structuredData)
<script type="application/ld+json">
{!! is_array($structuredData) ? json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : $structuredData !!}
</script>
@endif
