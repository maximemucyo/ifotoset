{{-- Testimonial quote cards. Vars: $testimonials, $center (bool) --}}
<div class="grid gap-5 {{ count($testimonials) === 1 ? 'mx-auto max-w-2xl' : 'md:grid-cols-2' }} {{ count($testimonials) >= 3 ? 'lg:grid-cols-3' : '' }}">
    @foreach($testimonials as $testimonial)
        <figure class="card flex flex-col p-6 sm:p-7 {{ ($center ?? false) ? 'text-center' : '' }}" data-reveal style="--reveal-delay: {{ ($loop->index % 3) * 80 }}ms">
            <x-icon name="quote" class="size-6 text-brand {{ ($center ?? false) ? 'mx-auto' : '' }}" />
            <blockquote class="mt-4 flex-1 whitespace-pre-line text-[15px] leading-relaxed text-foreground">{{ $testimonial['quote'] }}</blockquote>
            <figcaption class="mt-6 border-t border-border pt-4">
                <span class="block text-sm font-medium text-foreground">{{ $testimonial['author'] }}</span>
                @if($testimonial['context'])
                    <span class="block text-xs text-muted-foreground">{{ $testimonial['context'] }}</span>
                @endif
            </figcaption>
        </figure>
    @endforeach
</div>
