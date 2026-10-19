@props([
    'label',
    'value',
    'hint' => null,
    'change' => null,
    'changeType' => 'positive',
    'icon' => null,
    'href' => null,
])
@php($tag = $href ? 'a' : 'div')
<{{ $tag }} @if($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => 'card group relative p-5' . ($href ? ' card-hover' : '')]) }}>
    <div class="flex items-center justify-between gap-3">
        <span class="text-[13px] font-medium text-muted-foreground">{{ $label }}</span>
        @if($icon)
            <span class="flex size-7 items-center justify-center rounded-lg bg-muted text-muted-foreground transition-colors group-hover:text-foreground">
                <x-icon :name="$icon" class="size-3.5" />
            </span>
        @endif
    </div>
    <div class="mt-3 flex flex-wrap items-baseline gap-x-2 gap-y-1">
        <span class="stat-value">{{ $value }}</span>
        @if($change)
            <span class="inline-flex items-center gap-0.5 text-xs font-medium {{ $changeType === 'positive' ? 'text-success' : ($changeType === 'neutral' ? 'text-muted-foreground' : 'text-destructive') }}">
                <x-icon :name="$changeType === 'negative' ? 'trending-down' : 'trending-up'" class="size-3" />
                {{ $change }}
            </span>
        @endif
    </div>
    @if($hint)
        <p class="mt-1 text-xs text-muted-foreground">{{ $hint }}</p>
    @endif
    {{ $slot }}
</{{ $tag }}>
