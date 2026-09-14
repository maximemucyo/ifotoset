@props([
    'label',
    'value',
    'hint' => null,
    'change' => null,
    'changeType' => 'positive',
])

<div class="rounded-2xl border border-border bg-card p-6 shadow-sm hover:shadow-md transition-shadow">
    <div class="flex items-center justify-between">
        <span class="text-sm font-medium text-muted-foreground">{{ $label }}</span>
        @if($change)
            <span class="text-xs font-semibold px-2 py-0.5 rounded-full {{ $changeType === 'positive' ? 'bg-green-500/10 text-green-600' : 'bg-destructive/10 text-destructive' }}">
                {{ $change }}
            </span>
        @endif
    </div>
    <div class="mt-3 flex items-baseline gap-2">
        <span class="text-2xl sm:text-3xl font-bold tracking-tight text-foreground">{{ $value }}</span>
        @if($hint)
            <span class="text-xs text-muted-foreground">{{ $hint }}</span>
        @endif
    </div>
</div>
