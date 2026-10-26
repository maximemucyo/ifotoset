@extends('layouts.public', [
    'seo' => $seo ?? null,
    'hideNav' => true,
    'hideFooter' => true,
])

@section('content')
<div class="flex min-h-screen flex-col bg-background">
    <x-landing.navbar />

    <div class="flex-1">
        @yield('page')
        <x-landing.cta />
    </div>

    <x-landing.footer />
</div>
@endsection
