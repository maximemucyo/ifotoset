@props([
    'title' => 'No items found',
    'description' => 'Get started by creating your first entry.',
    'actionLabel' => null,
    'actionUrl' => null,
    'actionClick' => null,
    'icon' => null,
])

<div class="text-center py-16 px-6 rounded-2xl border border-dashed border-border bg-card/40 flex flex-col items-center justify-center">
    <div class="w-12 h-12 rounded-2xl bg-secondary/60 flex items-center justify-center text-primary mb-4">
        @if($icon)
            {!! $icon !!}
        @else
            <svg class="w-6 h-6 stroke-current" fill="none" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
            </svg>
        @endif
    </div>
    <h3 class="text-base font-bold text-foreground">{{ $title }}</h3>
    <p class="text-xs text-muted-foreground mt-1 max-w-sm">{{ $description }}</p>
    
    @if($actionLabel)
        <div class="mt-5">
            @if($actionUrl)
                <a href="{{ $actionUrl }}" class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-primary-foreground text-xs font-semibold rounded-xl shadow-sm hover:opacity-90 transition-opacity">
                    {{ $actionLabel }}
                </a>
            @elseif($actionClick)
                <button type="button" @click="{{ $actionClick }}" class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-primary-foreground text-xs font-semibold rounded-xl shadow-sm hover:opacity-90 transition-opacity">
                    {{ $actionLabel }}
                </button>
            @endif
        </div>
    @endif
</div>
