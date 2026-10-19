@php
    $flashMessage = session('success') ?? session('error') ?? session('warning') ?? session('info') ?? session('message');
    $flashType = session('success') ? 'success' : (session('error') ? 'error' : (session('warning') ? 'warning' : 'info'));
    if (is_array($flashMessage)) { $flashMessage = implode(' ', $flashMessage); }
@endphp
{{-- Global toast stack. Trigger from JS with window.toast('Saved', 'success'). --}}
<div x-data="toaster(@js((string) ($flashMessage ?? '')), @js($flashType))"
     class="pointer-events-none fixed inset-x-0 bottom-0 z-[100] flex flex-col items-center gap-2 p-4 sm:items-end sm:p-6"
     aria-live="polite">
    <template x-for="t in toasts" :key="t.id">
        <div x-show="t.visible"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-2 scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 scale-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0 translate-y-1"
             class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-xl border border-border bg-popover/95 p-3.5 pr-3 text-popover-foreground shadow-lg backdrop-blur-xl">
            <span class="mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full"
                  :class="{
                      'bg-success/15 text-success': t.type === 'success',
                      'bg-destructive/15 text-destructive': t.type === 'error',
                      'bg-warning/15 text-warning': t.type === 'warning',
                      'bg-info/15 text-info': t.type === 'info',
                  }">
                <x-icon name="check" class="size-3" stroke-width="3" x-show="t.type === 'success'" />
                <x-icon name="x" class="size-3" stroke-width="3" x-show="t.type === 'error'" />
                <x-icon name="alert-triangle" class="size-3" stroke-width="2.5" x-show="t.type === 'warning'" />
                <x-icon name="info" class="size-3" stroke-width="2.5" x-show="t.type === 'info'" />
            </span>
            <p class="flex-1 text-[13px] font-medium leading-5" x-text="t.message"></p>
            <button type="button" @click="dismiss(t.id)" class="-m-1 rounded-md p-1 text-muted-foreground transition hover:bg-muted hover:text-foreground" aria-label="Dismiss">
                <x-icon name="x" class="size-3.5" />
            </button>
        </div>
    </template>
</div>
