@props(['title', 'points' => [], 'reverse' => false])
{{-- Copy on one side, a screenshot (the media slot) on the other --}}
<div {{ $attributes->merge(['class' => 'grid items-center gap-10 lg:grid-cols-12 lg:gap-16']) }}>
    <div @class(['lg:col-span-5', 'lg:order-2' => $reverse])>
        <h2 class="text-[30px] font-semibold leading-[1.05] tracking-[-0.025em] text-foreground [font-stretch:90%] sm:text-[38px]">{{ $title }}</h2>
        <div class="mt-4 space-y-4 text-[16px] leading-relaxed text-muted-foreground">{{ $slot }}</div>
        @if($points)
            <ul class="mt-6 space-y-3 text-[15px] text-foreground">
                @foreach($points as $point)
                    <li class="flex gap-2.5"><x-icon name="check" class="mt-0.5 size-4 shrink-0 text-brand-strong" /> {{ $point }}</li>
                @endforeach
            </ul>
        @endif
    </div>
    <div @class(['lg:col-span-7', 'lg:order-1' => $reverse])>
        {{ $media }}
    </div>
</div>
