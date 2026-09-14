@props([
    'name',
    'title' => null,
    'maxWidth' => 'md',
])

@php
    $maxWidths = [
        'sm' => 'sm:max-w-sm',
        'md' => 'sm:max-w-md',
        'lg' => 'sm:max-w-lg',
        'xl' => 'sm:max-w-xl',
        '2xl' => 'sm:max-w-2xl',
    ];
    $maxWidthClass = $maxWidths[$maxWidth] ?? $maxWidths['md'];
@endphp

<div x-data="{ open: false }"
     x-show="open"
     x-on:open-modal.window="if ($event.detail === '{{ $name }}') open = true"
     x-on:close-modal.window="if ($event.detail === '{{ $name }}') open = false"
     x-on:keydown.escape.window="open = false"
     class="relative z-50"
     style="display: none;">
    <!-- Backdrop -->
    <div x-show="open"
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="open = false"
         class="fixed inset-0 bg-foreground/40 backdrop-blur-sm transition-opacity"></div>

    <!-- Dialog Panel -->
    <div class="fixed inset-0 z-10 overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
            <div x-show="open"
                 x-transition:enter="ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 class="relative transform overflow-hidden rounded-2xl bg-card border border-border p-6 text-left shadow-2xl transition-all w-full {{ $maxWidthClass }}">
                @if($title)
                    <div class="flex items-center justify-between pb-4 mb-4 border-b border-border">
                        <h3 class="text-lg font-semibold text-foreground">{{ $title }}</h3>
                        <button @click="open = false" class="text-muted-foreground hover:text-foreground text-lg leading-none">&times;</button>
                    </div>
                @endif

                {{ $slot }}
            </div>
        </div>
    </div>
</div>
