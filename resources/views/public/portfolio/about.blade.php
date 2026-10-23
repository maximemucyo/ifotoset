@extends('layouts.public', [
    'seo' => $seo ?? null,
    'hideNav' => true,
])

@php
    $aboutSection = $portfolio->section('about');
    $paragraphs = collect(preg_split('/\R{2,}/', trim($portfolio->aboutBody() ?? $photographer->bio ?? '')))->filter()->values();
    $socialLinks = $portfolio->socialLinks();
    $testimonials = $portfolio->testimonials();
    $portrait = $photographer->avatar_url;
@endphp

@section('content')
@include('public.partials.photographer-header', ['hasPackages' => $hasPackages, 'current' => 'about'])

<section class="relative">
    <div class="mx-auto max-w-6xl px-4 py-14 sm:px-6 sm:py-20 lg:px-8">
        <div class="grid gap-12 {{ $portrait ? 'lg:grid-cols-12 lg:gap-16' : '' }}">
            @if($portrait)
                <div class="lg:col-span-5">
                    <div class="mx-auto max-w-sm overflow-hidden rounded-2xl bg-muted lg:sticky lg:top-24 lg:max-w-none">
                        <img src="{{ $portrait }}" alt="{{ $photographer->name }}" fetchpriority="high" decoding="async" class="aspect-[4/5] size-full object-cover">
                    </div>
                </div>
            @endif
            <div class="{{ $portrait ? 'lg:col-span-7' : 'mx-auto max-w-2xl' }}">
                <p class="text-[11px] font-medium uppercase tracking-[0.28em] text-muted-foreground">{{ $aboutSection['eyebrow'] }}</p>
                <h1 class="pf-heading mt-3 text-5xl leading-[1.02] text-foreground sm:text-6xl">{{ $aboutSection['heading'] }}</h1>
                <p class="mt-5 text-sm text-muted-foreground">
                    {{ $photographer->name }} · {{ $portfolio->roleLabel() }}@if($photographer->location) · {{ $photographer->location }}@endif
                </p>

                @if($paragraphs->isNotEmpty())
                    <div class="mt-10 space-y-5 text-base leading-relaxed text-foreground/90 sm:text-[17px]">
                        @foreach($paragraphs as $paragraph)
                            <p class="whitespace-pre-line">{{ $paragraph }}</p>
                        @endforeach
                    </div>
                @else
                    <p class="mt-10 text-muted-foreground">{{ $photographer->name }} hasn't written an introduction yet.</p>
                @endif

                <div class="mt-10 flex flex-wrap items-center gap-3">
                    @if($hasPackages)
                        <a href="{{ route('subdomain.booking', ['username' => $photographer->username]) }}" class="btn btn-primary btn-lg">Book a session <x-icon name="arrow-right" /></a>
                    @endif
                    <a href="{{ $photographer->public_url }}/contact" class="btn {{ $hasPackages ? 'btn-outline' : 'btn-primary' }} btn-lg">Get in touch</a>
                </div>

                @if($socialLinks)
                    <div class="mt-8 flex items-center gap-2">
                        @foreach($socialLinks as $social)
                            <a href="{{ $social['url'] }}" target="_blank" rel="noopener noreferrer me" aria-label="{{ $social['label'] }}"
                               class="flex size-10 items-center justify-center rounded-full border border-border text-muted-foreground transition hover:border-border-strong hover:text-foreground">
                                <x-icon :name="$social['icon']" class="size-4" />
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</section>

@if($testimonials)
    @php($testimonialSection = $portfolio->section('testimonials'))
    <section class="border-t border-border/70 bg-muted/40">
        <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 sm:py-24 lg:px-8">
            @include('public.portfolio.partials.section-heading', ['section' => $testimonialSection, 'center' => true])
            <div class="mt-10">
                @include('public.portfolio.partials.testimonial-cards', ['testimonials' => $testimonials, 'center' => false])
            </div>
        </div>
    </section>
@endif

@include('public.partials.whatsapp-button')
@endsection

@section('footer')
    @include('public.partials.photographer-footer')
@endsection
