{{-- Shared package form fields. Vars: $prefix, $bind (Alpine object path in edit mode, or null). Parent must provide Alpine `depType`. --}}
@php
    $bind = $bind ?? null;
    $v = fn ($field, $default = '') => $bind
        ? ':value="' . $bind . '?.' . $field . ' ?? \'\'"'
        : ($default !== '' ? 'value="' . e($default) . '"' : '');
@endphp
<div class="space-y-4">
    <div>
        <label for="{{ $prefix }}-name" class="label">Package name <span class="text-destructive">*</span></label>
        <input id="{{ $prefix }}-name" type="text" name="name" required autofocus maxlength="255" {!! $v('name') !!} placeholder="e.g. Editorial portrait session" class="input">
    </div>

    <div class="grid grid-cols-2 gap-3 sm:grid-cols-[1fr_6rem_8rem]">
        <div class="col-span-2 sm:col-span-1">
            <label for="{{ $prefix }}-price" class="label">Price <span class="text-destructive">*</span></label>
            <input id="{{ $prefix }}-price" type="number" step="0.01" min="0" name="price" required {!! $v('price') !!} placeholder="75000" class="input tabular-nums">
        </div>
        <div>
            <label for="{{ $prefix }}-currency" class="label">Currency</label>
            <input id="{{ $prefix }}-currency" type="text" name="currency" required maxlength="10" {!! $v('currency', 'RWF') !!} class="input uppercase">
        </div>
        <div>
            <label for="{{ $prefix }}-duration" class="label">Duration</label>
            <div class="relative">
                <input id="{{ $prefix }}-duration" type="number" min="15" step="15" name="duration_minutes" required {!! $v('duration_minutes', '60') !!} class="input pr-11 tabular-nums">
                <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-muted-foreground">min</span>
            </div>
        </div>
    </div>

    <div>
        <label for="{{ $prefix }}-description" class="label">Description <span class="font-normal text-muted-foreground">(optional)</span></label>
        <textarea id="{{ $prefix }}-description" name="description" rows="2" {!! $v('description') !!} placeholder="What's this session about?" class="textarea"></textarea>
    </div>

    <div>
        <label for="{{ $prefix }}-deliverables" class="label">Deliverables</label>
        <textarea id="{{ $prefix }}-deliverables" name="deliverables_text" rows="3" {!! $v('deliverables_text') !!} placeholder="25 edited high-resolution photos&#10;Private online gallery&#10;Print release" class="textarea"></textarea>
        <p class="help">One per line. Shown as a checklist on your booking page.</p>
    </div>

    <div class="grid gap-3 sm:grid-cols-2">
        <div>
            <label for="{{ $prefix }}-deposit-type" class="label">Deposit</label>
            <select id="{{ $prefix }}-deposit-type" name="deposit_type" x-model="depType" class="select">
                <option value="none">No deposit</option>
                <option value="percentage">Percentage of price</option>
                <option value="fixed">Fixed amount</option>
            </select>
        </div>
        <div x-show="depType !== 'none'" x-transition.opacity>
            <label for="{{ $prefix }}-deposit-amount" class="label" x-text="depType === 'percentage' ? 'Deposit (%)' : 'Deposit amount'">Deposit amount</label>
            <input id="{{ $prefix }}-deposit-amount" type="number" step="0.01" min="0" name="deposit_amount" {!! $v('deposit_amount') !!} placeholder="e.g. 30" class="input tabular-nums">
        </div>
    </div>

    <label class="flex cursor-pointer items-center justify-between gap-4 rounded-lg border border-border bg-muted/40 px-3.5 py-3">
        <span>
            <span class="block text-sm font-medium text-foreground">Available for booking</span>
            <span class="block text-xs text-muted-foreground">Show this package on your public booking page.</span>
        </span>
        <span class="switch">
            <input type="checkbox" name="is_active" value="1" class="sr-only" @if($bind) :checked="{{ $bind }}?.is_active" @else checked @endif>
            <span></span>
        </span>
    </label>
</div>
