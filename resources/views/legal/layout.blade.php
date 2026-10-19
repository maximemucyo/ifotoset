@extends('layouts.public', ['title' => $pageTitle . ' · ifotoset', 'description' => $pageDescription])

@section('content')
<article class="mx-auto max-w-3xl px-4 py-16 sm:px-6 lg:py-24">
    <p class="text-[11px] font-medium uppercase tracking-[0.28em] text-muted-foreground">Legal</p>
    <h1 class="mt-3 font-serif text-4xl leading-tight text-foreground sm:text-5xl">{{ $pageTitle }}</h1>
    <p class="mt-3 text-sm text-muted-foreground">Last updated {{ $updated }}</p>

    <div class="legal-prose mt-10 space-y-8 text-[15px] leading-relaxed text-muted-foreground [&_h2]:mb-3 [&_h2]:text-lg [&_h2]:font-semibold [&_h2]:text-foreground [&_a]:text-foreground [&_a]:underline [&_a]:underline-offset-4 [&_li]:ml-5 [&_li]:list-disc [&_ul]:space-y-1.5">
        @yield('legal')
    </div>

    <p class="mt-12 border-t border-border pt-6 text-sm text-muted-foreground">
        Questions? Email <a href="mailto:support@ifotoset.com" class="text-foreground underline underline-offset-4">support@ifotoset.com</a>.
    </p>
</article>
@endsection
