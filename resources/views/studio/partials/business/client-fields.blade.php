{{-- Shared client form fields. Vars: $prefix (id prefix), $bind (Alpine object path for edit mode, or null) --}}
@php
    $bind = $bind ?? null;
    $v = fn ($field) => $bind ? ':value="' . $bind . '?.' . $field . ' ?? \'\'"' : '';
@endphp
<div class="space-y-4">
    <div>
        <label for="{{ $prefix }}-name" class="label">Full name <span class="text-destructive">*</span></label>
        <input id="{{ $prefix }}-name" type="text" name="name" required autofocus maxlength="255" {!! $v('name') !!} @if(!$bind) placeholder="e.g. Diane Mukamisha" @endif class="input">
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="{{ $prefix }}-email" class="label">Email</label>
            <div class="input-group">
                <x-icon name="mail" class="input-icon" />
                <input id="{{ $prefix }}-email" type="email" name="email" maxlength="255" {!! $v('email') !!} placeholder="name@example.com" class="input">
            </div>
        </div>
        <div>
            <label for="{{ $prefix }}-phone" class="label">Phone / WhatsApp</label>
            <div class="input-group">
                <x-icon name="phone" class="input-icon" />
                <input id="{{ $prefix }}-phone" type="tel" name="phone" maxlength="50" {!! $v('phone') !!} placeholder="+250 788 000 000" class="input">
            </div>
        </div>
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="{{ $prefix }}-company" class="label">Company</label>
            <div class="input-group">
                <x-icon name="building-2" class="input-icon" />
                <input id="{{ $prefix }}-company" type="text" name="company_name" maxlength="255" {!! $v('company_name') !!} placeholder="Optional" class="input">
            </div>
        </div>
        <div>
            <label for="{{ $prefix }}-location" class="label">Location</label>
            <div class="input-group">
                <x-icon name="map-pin" class="input-icon" />
                <input id="{{ $prefix }}-location" type="text" name="location" maxlength="255" {!! $v('location') !!} placeholder="Kigali, Rwanda" class="input">
            </div>
        </div>
    </div>
    <div>
        <label for="{{ $prefix }}-instagram" class="label">Instagram</label>
        <div class="input-group">
            <x-icon name="instagram" class="input-icon" />
            <input id="{{ $prefix }}-instagram" type="text" name="instagram" maxlength="100" {!! $v('instagram') !!} placeholder="@username" class="input">
        </div>
    </div>
    <div>
        <label for="{{ $prefix }}-notes" class="label">Private notes</label>
        <textarea id="{{ $prefix }}-notes" name="notes" rows="3" {!! $v('notes') !!} placeholder="Preferences, shoot style, reminders…" class="textarea"></textarea>
        <p class="help">Only visible to you.</p>
    </div>
</div>
