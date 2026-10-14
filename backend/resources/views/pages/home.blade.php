@extends('layouts.public', [
    'seo' => $seo ?? null,
    'hideNav' => true,
    'hideFooter' => true,
])

@section('content')
<div class="flex min-h-screen flex-col bg-background">
    <x-landing.navbar />

    <div class="flex-1">
        <x-landing.hero />
        <x-landing.product-suite />
        <x-landing.showcase-tabs />
        <x-landing.feature-grid />
        <x-landing.how-it-works />
        <x-landing.brand-statement />
        <x-landing.pricing :plans="$plans ?? collect()" />
        <x-landing.faq />
        <x-landing.cta />
    </div>

    <x-landing.footer />
</div>
@endsection
