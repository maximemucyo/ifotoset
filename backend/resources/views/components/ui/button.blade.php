@props([
    'variant' => 'primary',
    'size' => 'md',
    'type' => 'button',
])

@php
    $baseClasses = 'inline-flex items-center justify-center font-medium transition-all duration-150 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 disabled:opacity-50 disabled:pointer-events-none rounded-lg cursor-pointer';

    $variants = [
        'primary' => 'bg-primary text-primary-foreground hover:opacity-90 shadow-sm focus-visible:outline-primary',
        'secondary' => 'bg-secondary text-secondary-foreground hover:bg-secondary/80 focus-visible:outline-secondary',
        'outline' => 'border border-border bg-card text-foreground hover:bg-muted/50 focus-visible:outline-border',
        'destructive' => 'bg-destructive text-white hover:opacity-90 focus-visible:outline-destructive',
        'ghost' => 'text-foreground hover:bg-muted/50 focus-visible:outline-border',
    ];

    $sizes = [
        'sm' => 'text-xs px-2.5 py-1.5 gap-1.5',
        'md' => 'text-sm px-4 py-2 gap-2',
        'lg' => 'text-base px-5 py-2.5 gap-2.5',
    ];

    $classes = $baseClasses . ' ' . ($variants[$variant] ?? $variants['primary']) . ' ' . ($sizes[$size] ?? $sizes['md']);
@endphp

<button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</button>
