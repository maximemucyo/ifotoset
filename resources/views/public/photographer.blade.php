@extends('layouts.public', [
    'seo' => $seo ?? null,
    'hideNav' => true,
])

@php
    $template = $portfolio->template();
    $hasPackages = $packages->isNotEmpty();
    $bookUrl = route('subdomain.booking', ['username' => $photographer->username]);
    $websiteHost = $photographer->website ? (parse_url($photographer->website, PHP_URL_HOST) ?? $photographer->website) : null;

    $covers = $galleries
        ->map(fn ($g) => ($src = $g->getCoverUrl('lg')) ? ['src' => $src, 'srcset' => $g->getCoverSrcset()] : null)
        ->filter()
        ->values();
    // The uploaded cover leads; otherwise the first collection's cover
    $heroImage = $photographer->cover_url ? ['src' => $photographer->cover_url, 'srcset' => null] : $covers->first();
    $sideCovers = ($photographer->cover_url ? $covers : $covers->slice(1))->take(2)->values();

    // Per-template rhythm for the sections below the hero
    $style = [
        'editorial' => ['pad' => 'py-16 sm:py-24', 'alt' => 'bg-muted/40', 'border' => 'border-t border-border/70', 'center' => false],
        'minimal' => ['pad' => 'py-20 sm:py-32', 'alt' => '', 'border' => '', 'center' => true],
        'bold' => ['pad' => 'py-16 sm:py-28', 'alt' => 'bg-muted/60', 'border' => '', 'center' => false],
        'grid' => ['pad' => 'py-12 sm:py-16', 'alt' => 'bg-muted/40', 'border' => 'border-t border-border/70', 'center' => false],
    ][$template];

    // Sections with nothing to show are skipped so the alternating backgrounds stay even
    $sections = collect($portfolio->sections())
        ->filter(fn ($s) => $s['visible'] && match ($s['id']) {
            'about' => filled($portfolio->aboutBody()),
            'services' => $hasPackages,
            'testimonials' => !empty($portfolio->testimonials()),
            default => true,
        })
        ->values();
@endphp

@section('content')
<div data-template="{{ $template }}">
    @include('public.partials.photographer-header', ['hasPackages' => $hasPackages, 'onPortfolio' => true])

    @include('public.portfolio.sections.hero')

    @foreach($sections as $section)
        @include('public.portfolio.sections.' . $section['id'], [
            'section' => $section,
            'tone' => $loop->odd ? '' : $style['alt'],
        ])
    @endforeach
</div>

@include('public.partials.whatsapp-button')
@endsection

@section('footer')
    @include('public.partials.photographer-footer')
@endsection
