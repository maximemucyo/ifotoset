@props([
    'title' => 'No items found',
    'description' => 'Get started by creating your first entry.',
    'actionLabel' => null,
    'actionUrl' => null,
    'actionClick' => null,
    'icon' => null,
])
@php
    $iconName = ($icon && !str_contains($icon, '<')) ? $icon : null;
    $iconHtml = ($icon && str_contains($icon, '<')) ? $icon : null;
@endphp
<div {{ $attributes->merge(['class' => 'relative overflow-hidden rounded-2xl border border-dashed border-border-strong/70 bg-card/50 px-6 py-14 text-center']) }}>
    <div class="pointer-events-none absolute inset-0 bg-dots opacity-40 mask-radial"></div>
    <div class="relative mx-auto flex max-w-sm flex-col items-center">
        <div class="mb-4 flex size-12 items-center justify-center rounded-xl border border-border bg-card text-muted-foreground shadow-sm">
            @if($iconHtml)
                {!! $iconHtml !!}
            @else
                <x-icon :name="$iconName ?? 'inbox'" class="size-5" />
            @endif
        </div>
        <h3 class="text-[15px] font-semibold text-foreground">{{ $title }}</h3>
        <p class="mt-1.5 text-[13px] leading-relaxed text-muted-foreground">{{ $description }}</p>
        @if($actionLabel)
            <div class="mt-5">
                @if($actionUrl)
                    <a href="{{ $actionUrl }}" class="btn btn-primary btn-sm"><x-icon name="plus" />{{ $actionLabel }}</a>
                @elseif($actionClick)
                    <button type="button" @click="{{ $actionClick }}" class="btn btn-primary btn-sm"><x-icon name="plus" />{{ $actionLabel }}</button>
                @endif
            </div>
        @endif
        {{ $slot }}
    </div>
</div>
