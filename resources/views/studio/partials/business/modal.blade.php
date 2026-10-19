{{--
    Modal shell driven by an existing Alpine state flag on the page.
    Usage:
      @component('studio.partials.business.modal', ['show' => 'createModalOpen', 'title' => 'New client', 'description' => '…', 'icon' => 'user-plus', 'maxWidth' => 'lg'])
          …form…
      @endcomponent
    Inside, use `.bm-footer` markup (see below) for the action bar.
--}}
@php
    $close = $close ?? ($show . ' = false');
    $maxWidth = [
        'sm' => 'sm:max-w-sm', 'md' => 'sm:max-w-md', 'lg' => 'sm:max-w-lg', 'xl' => 'sm:max-w-xl', '2xl' => 'sm:max-w-2xl',
    ][$maxWidth ?? 'md'] ?? 'sm:max-w-md';
    $tone = $tone ?? 'default';
@endphp
<div x-show="{{ $show }}"
     x-on:keydown.escape.window="if ({{ $show }}) { {{ $close }} }"
     class="relative z-[80]" role="dialog" aria-modal="true" @if(!empty($title)) aria-label="{{ $title }}" @endif
     style="display: none;">
    <div x-show="{{ $show }}"
         x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         @click="{{ $close }}"
         class="fixed inset-0 bg-black/40 backdrop-blur-[2px]"></div>

    <div class="pointer-events-none fixed inset-0 overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-3 sm:items-center sm:p-4">
            <div x-show="{{ $show }}"
                 x-trap.noscroll="{{ $show }}"
                 x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-3 sm:translate-y-0 sm:scale-[0.97]" x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100 sm:scale-100" x-transition:leave-end="opacity-0 sm:scale-[0.97]"
                 class="pointer-events-auto relative w-full {{ $maxWidth }} max-h-[calc(100dvh-1.5rem)] overflow-y-auto overscroll-contain rounded-2xl sm:max-h-[calc(100dvh-2rem)] border border-border bg-popover text-left text-popover-foreground shadow-xl">
                @if(!empty($title))
                    <div class="flex items-start gap-3.5 px-5 pt-5 pb-1 sm:px-6">
                        @if(!empty($icon))
                            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg border {{ $tone === 'danger' ? 'border-destructive/20 bg-destructive/10 text-destructive' : 'border-border bg-muted text-foreground' }}">
                                <x-icon :name="$icon" class="size-4" />
                            </span>
                        @endif
                        <div class="min-w-0 flex-1 pt-0.5">
                            <h3 class="text-base font-semibold tracking-tight text-foreground">{{ $title }}</h3>
                            @if(!empty($description))
                                <p class="mt-1 text-[13px] leading-relaxed text-muted-foreground">{{ $description }}</p>
                            @endif
                        </div>
                        <button type="button" @click="{{ $close }}" class="btn btn-ghost btn-icon btn-sm -mr-2 -mt-1" aria-label="Close">
                            <x-icon name="x" />
                        </button>
                    </div>
                @endif
                {{ $slot }}
            </div>
        </div>
    </div>
</div>
