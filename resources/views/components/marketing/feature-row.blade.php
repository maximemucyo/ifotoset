@props(['eyebrow' => null, 'title', 'points' => [], 'reverse' => false])
{{-- Copy on one side, a screenshot (the media slot) on the other --}}
<div {{ $attributes->merge(['class' => 'grid items-center gap-10 lg:grid-cols-12 lg:gap-16']) }}>
    <div @class(['lg:col-span-5', 'lg:order-2' => $reverse]) data-reveal>
        @if($eyebrow)
            <p class="inline-flex items-center gap-2 text-[13px] font-medium text-brand">
                <span class="h-px w-5 bg-brand/50" aria-hidden="true"></span>{{ $eyebrow }}
            </p>
        @endif
        <h2 class="mt-3 text-[28px] font-semibold leading-[1.1] tracking-[-0.03em] text-foreground sm:text-[36px]">{{ $title }}</h2>
        <div class="mt-4 space-y-4 text-[15.5px] leading-relaxed text-muted-foreground">{{ $slot }}</div>
        @if($points)
            <ul class="mt-6 space-y-2.5 text-[14.5px] text-foreground">
                @foreach($points as $point)
                    <li class="flex gap-2.5"><x-icon name="check" class="mt-0.5 size-4 shrink-0 text-brand" /> {{ $point }}</li>
                @endforeach
            </ul>
        @endif
    </div>
    <div @class(['lg:col-span-7', 'lg:order-1' => $reverse]) data-reveal style="--reveal-delay: 100ms">
        {{ $media }}
    </div>
</div>
