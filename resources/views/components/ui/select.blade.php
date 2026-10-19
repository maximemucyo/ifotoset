@props([
    'label' => null,
    'id' => null,
    'name' => null,
    'error' => null,
    'hint' => null,
])
@php($id = $id ?? $name ?? 'select-' . uniqid())
<div>
    @if($label)
        <label for="{{ $id }}" class="label">{{ $label }}</label>
    @endif
    <select name="{{ $name }}" id="{{ $id }}" {{ $attributes->merge(['class' => 'select' . ($error ? ' input-error' : '')]) }}>
        {{ $slot }}
    </select>
    @if($error)
        <p class="error-text">{{ $error }}</p>
    @elseif($hint)
        <p class="help">{{ $hint }}</p>
    @endif
</div>
