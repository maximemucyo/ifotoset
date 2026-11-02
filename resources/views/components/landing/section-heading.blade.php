@props(['description' => null, 'tag' => 'h2'])
<div {{ $attributes->merge(['class' => 'max-w-2xl']) }}>
    <{{ $tag }} class="headline text-foreground">
        {{ $slot }}
    </{{ $tag }}>
    @if($description)
        <p class="mt-4 text-[17px] leading-relaxed text-muted-foreground">{{ $description }}</p>
    @endif
</div>
