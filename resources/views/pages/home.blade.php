@extends('layouts.public', [
    'seo' => $seo ?? null,
    'hideNav' => true,
    'hideFooter' => true,
    'forceTheme' => 'light',
])

{{-- iOS Safari tints the status bar strip from the body background; match the top of the cover photo.
     The .marketing wrapper keeps the page itself light. --}}
@push('styles')
    <style>body { background-color: #3b3420; }</style>
@endpush

@section('content')
<div class="marketing flex min-h-screen flex-col bg-background">
    <x-landing.navbar overlay />

    <div class="flex-1">
        <x-landing.hero />
        <x-landing.product-suite />
        <x-landing.mobile-money :plans="$plans ?? collect()" />
        <x-landing.feature-grid />
        <x-landing.how-it-works />
        <x-landing.pricing :plans="$plans ?? collect()" />
        <x-landing.faq :plans="$plans ?? collect()" :limit="5" />
        <x-landing.cta />
    </div>

    <x-landing.footer />
</div>
@endsection
