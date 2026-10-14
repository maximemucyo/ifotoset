@props(['href' => null, 'size' => 'md', 'wordmark' => true])
@php
    $mark = ['sm' => 'size-6', 'md' => 'size-7', 'lg' => 'size-9'][$size] ?? 'size-7';
    $text = ['sm' => 'text-[15px]', 'md' => 'text-[17px]', 'lg' => 'text-xl'][$size] ?? 'text-[17px]';
    $tag = $href ? 'a' : 'span';
@endphp
<{{ $tag }} @if($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => 'group inline-flex items-center gap-2']) }}>
    <span class="relative inline-flex {{ $mark }} items-center justify-center rounded-[28%] bg-foreground text-background shadow-sm">
        {{-- Aperture-inspired mark --}}
        <svg viewBox="0 0 32 32" class="size-[62%]" fill="none" aria-hidden="true">
            <circle cx="16" cy="16" r="11" stroke="currentColor" stroke-width="2.6"/>
            <circle cx="16" cy="16" r="4.2" fill="var(--brand)"/>
            <path d="M16 5v6.5M27 16h-6.5M16 27v-6.5M5 16h6.5" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" opacity=".35"/>
        </svg>
    </span>
    @if($wordmark)
        <span class="font-display {{ $text }} font-semibold tracking-tight text-foreground">ifotoset</span>
    @endif
    {{ $slot }}
</{{ $tag }}>
