@props([
    'name',
    'title' => null,
    'description' => null,
    'maxWidth' => 'md',
])
@php
    $maxWidths = [
        'sm' => 'sm:max-w-sm',
        'md' => 'sm:max-w-md',
        'lg' => 'sm:max-w-lg',
        'xl' => 'sm:max-w-xl',
        '2xl' => 'sm:max-w-2xl',
        '3xl' => 'sm:max-w-3xl',
    ];
    $maxWidthClass = $maxWidths[$maxWidth] ?? $maxWidths['md'];
@endphp
{{-- Open with $dispatch('open-modal', '{{ $name }}'), close with $dispatch('close-modal', '{{ $name }}') --}}
<div x-data="{ open: false }"
     x-show="open"
     x-on:open-modal.window="if ($event.detail === '{{ $name }}') open = true"
     x-on:close-modal.window="if ($event.detail === '{{ $name }}') open = false"
     x-on:keydown.escape.window="open = false"
     x-trap.noscroll="open"
     class="relative z-[80]"
     role="dialog" aria-modal="true"
     style="display: none;">
    <div x-show="open"
         x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         @click="open = false"
         class="fixed inset-0 bg-black/40 backdrop-blur-[2px]"></div>

    <div class="fixed inset-0 overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 sm:items-center">
            <div x-show="open"
                 x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-3 sm:translate-y-0 sm:scale-[0.97]" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100 sm:scale-100" x-transition:leave-end="opacity-0 sm:scale-[0.97]"
                 class="relative w-full {{ $maxWidthClass }} max-h-[calc(100dvh-2rem)] overflow-y-auto overscroll-contain rounded-2xl border border-border bg-popover text-left text-popover-foreground shadow-xl">
                @if($title)
                    <div class="flex items-start justify-between gap-4 px-6 pt-5 pb-1">
                        <div>
                            <h3 class="text-base font-semibold text-foreground">{{ $title }}</h3>
                            @if($description)
                                <p class="mt-1 text-[13px] text-muted-foreground">{{ $description }}</p>
                            @endif
                        </div>
                        <button type="button" @click="open = false" class="btn btn-ghost btn-icon btn-sm -mr-2 -mt-1" aria-label="Close">
                            <x-icon name="x" />
                        </button>
                    </div>
                @endif
                <div class="px-6 pb-6 {{ $title ? 'pt-4' : 'pt-6' }}">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </div>
</div>
