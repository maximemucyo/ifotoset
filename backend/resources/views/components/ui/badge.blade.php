@props([
    'variant' => 'default',
    'dot' => false,
])
@php
    $variants = [
        'default' => 'badge-neutral',
        'neutral' => 'badge-neutral',
        'muted' => 'badge-neutral',
        'brand' => 'badge-brand',
        'primary' => 'badge-brand',
        'success' => 'badge-success',
        'warning' => 'badge-warning',
        'destructive' => 'badge-danger',
        'danger' => 'badge-danger',
        'info' => 'badge-info',
        'solid' => 'badge-solid',
    ];
    $classes = 'badge ' . ($variants[$variant] ?? 'badge-neutral') . ($dot ? ' badge-dot' : '');
@endphp
<span {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</span>
