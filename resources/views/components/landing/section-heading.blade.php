@props(['eyebrow' => null, 'description' => null, 'align' => 'center', 'tag' => 'h2'])
<div {{ $attributes->merge(['class' => ($align === 'center' ? 'mx-auto max-w-3xl text-center' : 'max-w-2xl')]) }} data-reveal>
    @if($eyebrow)
        <p class="inline-flex items-center gap-2 text-[13px] font-medium text-brand">
            <span class="h-px w-5 bg-brand/50" aria-hidden="true"></span>{{ $eyebrow }}
        </p>
    @endif
    <{{ $tag }} class="mt-3 text-[32px] font-semibold leading-[1.08] tracking-[-0.03em] text-foreground sm:text-[44px]">
        {{ $slot }}
    </{{ $tag }}>
    @if($description)
        <p class="mt-4 text-base leading-relaxed text-muted-foreground sm:text-[17px] {{ $align === 'center' ? 'mx-auto max-w-2xl' : '' }}">{{ $description }}</p>
    @endif
</div>
