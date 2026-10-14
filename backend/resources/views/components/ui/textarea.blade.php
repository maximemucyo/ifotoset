@props([
    'label' => null,
    'id' => null,
    'name' => null,
    'error' => null,
    'hint' => null,
    'value' => null,
])
@php($id = $id ?? $name ?? 'textarea-' . uniqid())
<div>
    @if($label)
        <label for="{{ $id }}" class="label">{{ $label }}</label>
    @endif
    <textarea name="{{ $name }}" id="{{ $id }}" {{ $attributes->merge(['class' => 'textarea' . ($error ? ' input-error' : ''), 'rows' => 4]) }}>{{ $value ?? $slot }}</textarea>
    @if($error)
        <p class="error-text">{{ $error }}</p>
    @elseif($hint)
        <p class="help">{{ $hint }}</p>
    @endif
</div>
