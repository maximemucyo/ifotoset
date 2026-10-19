@props(['href' => null, 'size' => 'md', 'wordmark' => true])
@php
    $mark = ['sm' => 'size-7', 'md' => 'size-8', 'lg' => 'size-10'][$size] ?? 'size-8';
    $text = ['sm' => 'text-[16px]', 'md' => 'text-[18px]', 'lg' => 'text-[22px]'][$size] ?? 'text-[18px]';
    $tag = $href ? 'a' : 'span';
@endphp
<{{ $tag }} @if($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => 'group inline-flex items-center gap-2']) }}>
    <img src="{{ asset('logo-mark.webp') }}"
         alt="{{ $wordmark ? '' : 'ifotoset' }}"
         width="112" height="112"
         decoding="async"
         class="{{ $mark }} shrink-0 object-contain drop-shadow-sm transition-transform duration-500 ease-out group-hover:rotate-[30deg]">
    @if($wordmark)
        <span class="font-display {{ $text }} font-bold leading-none tracking-tight text-foreground">ifoto<span class="text-brand">set</span></span>
    @endif
    {{ $slot }}
</{{ $tag }}>
