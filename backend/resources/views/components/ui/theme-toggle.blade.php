@props(['variant' => 'icon'])
@if($variant === 'segmented')
    <div {{ $attributes->merge(['class' => 'tabs']) }} x-data role="group" aria-label="Theme">
        @foreach(['light' => 'sun', 'dark' => 'moon', 'system' => 'monitor'] as $mode => $icon)
            <button type="button" class="tab px-2" :aria-pressed="($store.theme.mode === '{{ $mode }}').toString()" :class="$store.theme.mode === '{{ $mode }}' && 'is-active'" @click="$store.theme.set('{{ $mode }}')" title="{{ ucfirst($mode) }}" aria-label="{{ ucfirst($mode) }} theme">
                <x-icon :name="$icon" />
            </button>
        @endforeach
    </div>
@else
    <button type="button" x-data @click="$store.theme.toggle()" {{ $attributes->merge(['class' => 'btn btn-ghost btn-icon btn-sm']) }} aria-label="Toggle theme" title="Toggle theme">
        <x-icon name="sun" class="hidden dark:block" />
        <x-icon name="moon" class="block dark:hidden" />
    </button>
@endif
