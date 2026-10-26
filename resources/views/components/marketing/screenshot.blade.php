@props(['name', 'alt', 'url' => null, 'width' => 1600, 'height' => 1000, 'dark' => true, 'priority' => false])
@php
    $src = fn (string $theme) => asset("images/marketing/screens/{$name}-{$theme}.webp");
    $loading = $priority ? 'fetchpriority=high' : 'loading=lazy';
@endphp
{{-- A real product screenshot in a browser frame, with a dark-mode capture when one exists --}}
<figure {{ $attributes->merge(['class' => 'overflow-hidden rounded-2xl border border-border bg-card shadow-xl ring-1 ring-foreground/[0.03] dark:shadow-black/40']) }}>
    <div class="flex items-center gap-3 border-b border-border bg-muted/60 px-3 py-2.5 sm:px-4" aria-hidden="true">
        <div class="flex gap-1.5">
            <span class="size-2.5 rounded-full bg-border-strong"></span>
            <span class="size-2.5 rounded-full bg-border-strong"></span>
            <span class="size-2.5 rounded-full bg-border-strong"></span>
        </div>
        @if($url)
            <div class="mx-auto flex h-7 w-full max-w-sm items-center justify-center gap-1.5 rounded-md border border-border bg-card px-3 text-[11.5px] text-muted-foreground">
                <x-icon name="lock" class="size-3 text-success" />
                <span class="truncate">{{ $url }}</span>
            </div>
            <div class="hidden w-[42px] sm:block"></div>
        @endif
    </div>
    <img src="{{ $src('light') }}" width="{{ $width }}" height="{{ $height }}" alt="{{ $alt }}" {{ $loading }} decoding="async" @class(['block h-auto w-full', 'dark:hidden' => $dark])>
    @if($dark)
        <img src="{{ $src('dark') }}" width="{{ $width }}" height="{{ $height }}" alt="{{ $alt }}" loading="lazy" decoding="async" class="hidden h-auto w-full dark:block">
    @endif
</figure>
