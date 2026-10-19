@props([
    'variant' => 'primary',
    'size' => 'md',
    'type' => 'button',
    'href' => null,
    'icon' => null,
    'iconRight' => null,
])
@php
    $variants = [
        'primary' => 'btn-primary',
        'brand' => 'btn-brand',
        'secondary' => 'btn-secondary',
        'outline' => 'btn-outline',
        'ghost' => 'btn-ghost',
        'destructive' => 'btn-danger',
        'danger' => 'btn-danger',
        'link' => 'btn-link',
    ];
    $sizes = ['xs' => 'btn-xs', 'sm' => 'btn-sm', 'md' => '', 'lg' => 'btn-lg', 'xl' => 'btn-xl'];
    $classes = trim('btn ' . ($variants[$variant] ?? 'btn-primary') . ' ' . ($sizes[$size] ?? ''));
@endphp
@if($href)
<a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
    @if($icon)<x-icon :name="$icon" />@endif
    {{ $slot }}
    @if($iconRight)<x-icon :name="$iconRight" />@endif
</a>
@else
<button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
    @if($icon)<x-icon :name="$icon" />@endif
    {{ $slot }}
    @if($iconRight)<x-icon :name="$iconRight" />@endif
</button>
@endif
