{{-- Client testimonials. Vars from public/photographer + $section, $tone --}}
<section id="testimonials" class="scroll-mt-16 {{ $style['border'] }} {{ $tone }}">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 {{ $style['pad'] }}">
        @include('public.portfolio.partials.section-heading', ['center' => $style['center']])
        <div class="mt-10">
            @include('public.portfolio.partials.testimonial-cards', ['testimonials' => $portfolio->testimonials(), 'center' => $style['center']])
        </div>
    </div>
</section>
