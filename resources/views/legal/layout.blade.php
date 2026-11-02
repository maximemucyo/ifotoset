@extends('layouts.public', ['title' => $pageTitle . ' · ifotoset', 'description' => $pageDescription, 'forceTheme' => 'light', 'hideNav' => true, 'hideFooter' => true])

@php
    $pages = [
        ['route' => 'legal.terms', 'label' => 'Terms of Service'],
        ['route' => 'legal.privacy', 'label' => 'Privacy Policy'],
    ];
@endphp

@section('content')
<div class="marketing flex min-h-screen flex-col bg-background">
    <x-landing.navbar />

    <article class="mx-auto w-full max-w-3xl flex-1 px-4 pb-16 pt-10 sm:px-6 sm:pb-24 sm:pt-16">
        {{-- Switch between the two documents --}}
        <nav aria-label="Legal documents" class="inline-flex rounded-xl bg-muted p-1">
            @foreach($pages as $page)
                <a href="{{ route($page['route']) }}" @if(request()->routeIs($page['route'])) aria-current="page" @endif @class([
                    'inline-flex h-10 items-center rounded-lg px-3.5 text-[14px] font-medium transition-colors',
                    'bg-card text-foreground shadow-sm' => request()->routeIs($page['route']),
                    'text-muted-foreground hover:text-foreground' => !request()->routeIs($page['route']),
                ])>{{ $page['label'] }}</a>
            @endforeach
        </nav>

        <h1 class="headline mt-8">{{ $pageTitle }}</h1>
        <p class="mt-3 text-[15px] text-muted-foreground">Last updated {{ $updated }}</p>

        <div class="legal-prose mt-10 max-w-[68ch] space-y-10 text-[16px] leading-[1.7] text-secondary-foreground [&_a]:font-medium [&_a]:text-foreground [&_a]:underline [&_a]:decoration-brand [&_a]:decoration-2 [&_a]:underline-offset-4 [&_h2]:mb-3 [&_h2]:text-[22px] [&_h2]:font-semibold [&_h2]:leading-tight [&_h2]:text-foreground [&_li]:ml-5 [&_li]:list-disc [&_li]:pl-1 [&_li]:marker:text-subtle [&_p+p]:mt-3 [&_p+ul]:mt-3 [&_strong]:font-semibold [&_strong]:text-foreground [&_ul+p]:mt-3 [&_ul]:space-y-2">
            @yield('legal')
        </div>

        <p class="mt-14 border-t border-border pt-6 text-[15px] text-muted-foreground">
            Questions about this page? Email <a href="mailto:support@ifotoset.com" class="font-medium text-foreground underline decoration-brand decoration-2 underline-offset-4">support@ifotoset.com</a>.
        </p>
    </article>

    <x-landing.footer />
</div>
@endsection
