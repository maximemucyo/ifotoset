@extends('layouts.public', ['title' => 'Site not published · ifotoset', 'robots' => 'noindex, nofollow', 'hideNav' => true])

@php
    $home = app(\App\Services\PublicUrlService::class)->homeUrl();
    // Sessions are per host, so send owners to Settings on the main site; it asks them to sign in first if needed
    $publishUrl = rtrim($home, '/') . route('studio.settings.index', [], false) . '#public';
@endphp

@section('content')
<section class="flex min-h-[calc(100vh-5rem)] items-center justify-center px-4 py-20 sm:px-6">
    <div class="mx-auto w-full max-w-md text-center">
        <h1 class="text-2xl font-semibold tracking-[-0.02em] text-foreground sm:text-3xl">This site isn't published yet</h1>
        <p class="mt-6 text-[15px] leading-relaxed text-muted-foreground">
            If you're the owner of this site, turn on Public portfolio in your <a href="{{ $publishUrl }}" class="link">Settings</a> to publish it.
            Have a gallery link from this photographer? It still works.
        </p>
    </div>
</section>
@endsection

@section('footer')
<footer class="mt-auto">
    <div class="flex items-center justify-between gap-4 px-4 py-6 text-[13px] text-muted-foreground sm:px-8">
        <span class="truncate">{{ request()->getHost() }}</span>
        <span class="shrink-0">Powered by <a href="{{ $home }}" class="link">ifotoset</a></span>
    </div>
</footer>
@endsection
