@extends('layouts.public', [
    'seo' => $seo ?? null,
    'hideNav' => true,
    'hideFooter' => true,
])

@section('content')
<div class="flex flex-col min-h-screen bg-background">
    <x-landing.navbar />

    <main class="flex-grow">
        <x-landing.hero />
        <x-landing.product-suite />
        <x-landing.showcase-tabs />
        <x-landing.feature-grid />
        <x-landing.brand-statement />
        <x-landing.pricing />
        <x-landing.faq />
        <x-landing.cta />
    </main>

    <x-landing.footer />
</div>
@endsection
