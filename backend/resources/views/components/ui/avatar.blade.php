@props(['name' => '', 'src' => null, 'size' => 'md'])
@php
    $sizes = ['xs' => 'size-6 text-[10px]', 'sm' => 'size-8 text-xs', 'md' => 'size-9 text-[13px]', 'lg' => 'size-12 text-base', 'xl' => 'size-16 text-xl'];
    $words = preg_split('/\s+/', trim((string) $name)) ?: [];
    $initials = strtoupper(mb_substr($words[0] ?? '?', 0, 1) . (count($words) > 1 ? mb_substr(end($words), 0, 1) : ''));
    $hues = [18, 32, 200, 262, 152, 340, 220, 45];
    $hue = $hues[abs(crc32((string) $name)) % count($hues)];
@endphp
<span {{ $attributes->merge(['class' => 'avatar ' . ($sizes[$size] ?? $sizes['md']) . ($src ? '' : ' avatar-initials')]) }}
      @if(!$src) style="--avatar-hue: {{ $hue }};" @endif>
    @if($src)
        <img src="{{ $src }}" alt="{{ $name }}" class="size-full object-cover" loading="lazy" decoding="async">
    @else
        {{ $initials ?: '?' }}
    @endif
</span>
