@props([
    'label' => null,
    'id' => null,
    'name' => 'phone',
    'value' => null,
    'error' => null,
    'hint' => null,
])
@php
    $id = $id ?? $name;
    $countries = \App\Support\Phone::countries();
    // Old input holds what was typed; a saved value is E.164 and is split back into country + number
    $current = old($name) !== null
        ? ['country' => old('phone_country', \App\Support\Phone::DEFAULT_COUNTRY), 'national' => old($name)]
        : \App\Support\Phone::split($value);
    $selected = isset($countries[$current['country']]) ? $current['country'] : \App\Support\Phone::DEFAULT_COUNTRY;
@endphp
<div x-data="{ country: @js($selected), countries: @js(collect($countries)->map(fn ($c) => $c['flag'] . ' ' . $c['dial'])) }">
    @if($label)
        <label for="{{ $id }}" class="label">{{ $label }}</label>
    @endif
    <div class="flex items-stretch overflow-hidden rounded-lg border border-border bg-input shadow-xs transition focus-within:border-ring focus-within:ring-4 focus-within:ring-ring/15 {{ $error ? '!border-destructive' : '' }}">
        <div class="relative flex shrink-0 items-center gap-1 border-r border-border bg-muted px-3 text-[13px] text-muted-foreground">
            <span class="tabular-nums" x-text="countries[country]">{{ $countries[$selected]['flag'] }} {{ $countries[$selected]['dial'] }}</span>
            <x-icon name="chevron-down" class="size-3.5 text-subtle" />
            <select name="phone_country" x-model="country" aria-label="Country code" class="absolute inset-0 cursor-pointer opacity-0">
                @foreach($countries as $code => $country)
                    <option value="{{ $code }}" @selected($code === $selected)>{{ $country['name'] }} ({{ $country['dial'] }})</option>
                @endforeach
            </select>
        </div>
        <input type="tel" name="{{ $name }}" id="{{ $id }}" value="{{ $current['national'] }}" autocomplete="tel-national" inputmode="tel"
               @if($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
               {{ $attributes->merge(['class' => 'h-10 min-w-0 flex-1 bg-transparent px-3 text-sm text-foreground outline-none placeholder:text-subtle']) }}>
    </div>
    @if($error)
        <p id="{{ $id }}-error" class="error-text">{{ $error }}</p>
    @elseif($hint)
        <p class="help">{{ $hint }}</p>
    @endif
</div>
