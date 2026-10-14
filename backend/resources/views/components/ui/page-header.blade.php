@props([
    'title',
    'description' => null,
    'eyebrow' => null,
    'back' => null,
    'backLabel' => 'Back',
])
<div {{ $attributes->merge(['class' => 'mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between']) }}>
    <div class="min-w-0">
        @if($back)
            <a href="{{ $back }}" class="mb-3 inline-flex items-center gap-1.5 text-[13px] font-medium text-muted-foreground transition hover:text-foreground">
                <x-icon name="arrow-left" class="size-3.5" /> {{ $backLabel }}
            </a>
        @endif
        @if($eyebrow)
            <p class="eyebrow mb-2">{{ $eyebrow }}</p>
        @endif
        <h1 class="truncate font-display text-2xl font-semibold tracking-tight text-foreground sm:text-[28px]">{{ $title }}</h1>
        @if($description)
            <p class="mt-1.5 max-w-2xl text-sm text-muted-foreground">{{ $description }}</p>
        @endif
        {{ $meta ?? '' }}
    </div>
    @isset($actions)
        <div class="flex shrink-0 flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
