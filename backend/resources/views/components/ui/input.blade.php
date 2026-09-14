@props([
    'label' => null,
    'id' => null,
    'name' => null,
    'type' => 'text',
    'error' => null,
])

@php
    $id = $id ?? $name ?? 'input-' . uniqid();
@endphp

<div class="space-y-1.5">
    @if($label)
        <label for="{{ $id }}" class="block text-sm font-medium text-foreground">
            {{ $label }}
        </label>
    @endif
    <input type="{{ $type }}"
           name="{{ $name }}"
           id="{{ $id }}"
           {{ $attributes->merge([
               'class' => 'block w-full rounded-lg border bg-input px-3.5 py-2 text-foreground placeholder:text-muted-foreground shadow-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary text-sm transition-colors ' . ($error ? 'border-destructive' : 'border-border')
           ]) }}>
    @if($error)
        <p class="text-xs text-destructive mt-1">{{ $error }}</p>
    @endif
</div>
