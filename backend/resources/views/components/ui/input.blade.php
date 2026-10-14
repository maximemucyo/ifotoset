@props([
    'label' => null,
    'id' => null,
    'name' => null,
    'type' => 'text',
    'error' => null,
    'hint' => null,
    'icon' => null,
])
@php
    $id = $id ?? $name ?? 'input-' . uniqid();
    $isPassword = $type === 'password';
@endphp
<div {{ $attributes->only('x-show')->merge(['class' => '']) }}>
    @if($label)
        <label for="{{ $id }}" class="label">{{ $label }}</label>
    @endif
    <div class="input-group" @if($isPassword) x-data="{ reveal: false }" @endif>
        @if($icon)
            <x-icon :name="$icon" class="input-icon" />
        @endif
        <input @if($isPassword) :type="reveal ? 'text' : 'password'" type="password" @else type="{{ $type }}" @endif
               name="{{ $name }}"
               id="{{ $id }}"
               @if($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
               {{ $attributes->except('x-show')->merge(['class' => 'input' . ($error ? ' input-error' : '') . ($isPassword ? ' pr-10' : '')]) }}>
        @if($isPassword)
            <button type="button" @click="reveal = !reveal" class="absolute right-1.5 flex size-7 items-center justify-center rounded-md text-subtle transition hover:bg-muted hover:text-foreground" :aria-label="reveal ? 'Hide password' : 'Show password'" tabindex="-1">
                <x-icon name="eye" class="size-4" x-show="!reveal" />
                <x-icon name="eye-off" class="size-4" x-show="reveal" x-cloak />
            </button>
        @endif
    </div>
    @if($error)
        <p id="{{ $id }}-error" class="error-text">{{ $error }}</p>
    @elseif($hint)
        <p class="help">{{ $hint }}</p>
    @endif
</div>
