@props([
    'title' => null,
    'description' => null,
    'padding' => true,
])
<section {{ $attributes->merge(['class' => 'card']) }}>
    @if($title || isset($actions))
        <header class="card-header">
            <div class="min-w-0">
                @if($title)<h2 class="card-title">{{ $title }}</h2>@endif
                @if($description)<p class="card-description mt-0.5">{{ $description }}</p>@endif
            </div>
            @isset($actions)
                <div class="flex shrink-0 items-center gap-2">{{ $actions }}</div>
            @endisset
        </header>
    @endif
    <div class="{{ $padding ? 'card-body' : '' }}">
        {{ $slot }}
    </div>
    @isset($footer)
        <footer class="card-footer">{{ $footer }}</footer>
    @endisset
</section>
