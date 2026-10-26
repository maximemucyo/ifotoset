@props(['name', 'alt', 'dark' => true])
@php($src = fn (string $theme) => asset("images/marketing/screens/{$name}-{$theme}.webp"))
{{-- A real mobile screenshot in a phone frame --}}
<figure {{ $attributes->merge(['class' => 'w-full max-w-[260px] rounded-[2.25rem] border border-border bg-background p-2 shadow-xl dark:shadow-black/40']) }}>
    <div class="overflow-hidden rounded-[1.8rem] border border-border">
        <img src="{{ $src('light') }}" width="780" height="1688" alt="{{ $alt }}" loading="lazy" decoding="async" @class(['block h-auto w-full', 'dark:hidden' => $dark])>
        @if($dark)
            <img src="{{ $src('dark') }}" width="780" height="1688" alt="{{ $alt }}" loading="lazy" decoding="async" class="hidden h-auto w-full dark:block">
        @endif
    </div>
</figure>
