{{--
    Page-level confirmation dialog for destructive form submissions.
    Trigger from any form:
      <form … @submit.prevent="$dispatch('confirm-action', { form: $el, title: '…', message: '…', confirm: 'Delete' })">
    On confirm the original form is submitted natively (fields, @csrf and @method intact).
--}}
<div x-data="{
        open: false, busy: false, form: null,
        title: '', message: '', confirm: 'Confirm', cancel: 'Cancel', tone: 'danger', icon: 'triangle-alert',
        ask(d) {
            this.form = d.form; this.title = d.title || 'Are you sure?'; this.message = d.message || '';
            this.confirm = d.confirm || 'Confirm'; this.cancel = d.cancel || 'Cancel'; this.tone = d.tone || 'danger'; this.icon = d.icon || (this.tone === 'danger' ? 'triangle-alert' : 'circle-help');
            this.busy = false; this.open = true;
        },
        go() { if (!this.form || this.busy) return; this.busy = true; HTMLFormElement.prototype.submit.call(this.form); }
     }"
     x-on:confirm-action.window="ask($event.detail)">
    @component('studio.partials.business.modal', ['show' => 'open', 'close' => 'if (!busy) open = false', 'maxWidth' => 'sm'])
        <div class="p-5 sm:p-6">
            <div class="flex items-start gap-3.5">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-full"
                      :class="tone === 'danger' ? 'bg-destructive/10 text-destructive' : 'bg-muted text-foreground'">
                    <x-icon name="triangle-alert" class="size-[18px]" x-show="icon === 'triangle-alert'" />
                    <x-icon name="circle-help" class="size-[18px]" x-show="icon === 'circle-help'" x-cloak />
                    <x-icon name="rotate-ccw" class="size-[18px]" x-show="icon === 'rotate-ccw'" x-cloak />
                    <x-icon name="check-circle" class="size-[18px]" x-show="icon === 'check-circle'" x-cloak />
                </span>
                <div class="min-w-0 pt-0.5">
                    <h3 class="text-base font-semibold tracking-tight text-foreground" x-text="title"></h3>
                    <p class="mt-1.5 text-[13px] leading-relaxed text-muted-foreground" x-text="message"></p>
                </div>
            </div>
        </div>
        <div class="flex flex-col-reverse gap-2 border-t border-border bg-muted/40 px-5 py-3.5 sm:flex-row sm:justify-end sm:px-6">
            <button type="button" class="btn btn-outline" @click="open = false" :disabled="busy" x-text="cancel">Cancel</button>
            <button type="button" class="btn" :class="tone === 'danger' ? 'btn-danger' : 'btn-primary'" @click="go()" :disabled="busy">
                <x-icon name="loader-circle" class="animate-spin" x-show="busy" x-cloak />
                <span x-text="confirm"></span>
            </button>
        </div>
    @endcomponent
</div>
