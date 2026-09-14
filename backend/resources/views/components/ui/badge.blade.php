@props([
    'variant' => 'default',
])

@php
    $variants = [
        'default' => 'bg-primary/10 text-primary border-primary/20',
        'success' => 'bg-green-500/10 text-green-700 dark:text-green-400 border-green-500/20',
        'warning' => 'bg-amber-500/10 text-amber-700 dark:text-amber-400 border-amber-500/20',
        'destructive' => 'bg-destructive/10 text-destructive border-destructive/20',
        'muted' => 'bg-muted text-muted-foreground border-border',
    ];

    $classes = 'inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-semibold ' . ($variants[$variant] ?? $variants['default']);
@endphp

<span {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</span>
