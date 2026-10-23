{{-- About teaser linking to the full About page. Vars from public/photographer + $section, $tone --}}
@php
    $aboutParagraphs = preg_split('/\R{2,}/', trim($portfolio->aboutBody()));
    $aboutLead = Str::limit($aboutParagraphs[0], 520);
    $portrait = $photographer->avatar_url;
@endphp
<section id="about" class="scroll-mt-16 {{ $style['border'] }} {{ $tone }}">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 {{ $style['pad'] }}">
        <div class="grid items-center gap-10 {{ $portrait && !$style['center'] ? 'lg:grid-cols-12 lg:gap-16' : '' }}">
            @if($portrait && !$style['center'])
                <div class="lg:col-span-5" data-reveal>
                    <div class="mx-auto max-w-sm overflow-hidden rounded-2xl bg-muted lg:max-w-none">
                        <img src="{{ $portrait }}" alt="{{ $photographer->name }}" loading="lazy" decoding="async" class="aspect-square size-full object-cover">
                    </div>
                </div>
            @endif
            <div class="{{ $portrait && !$style['center'] ? 'lg:col-span-7' : '' }}" data-reveal>
                @include('public.portfolio.partials.section-heading', ['center' => $style['center']])
                <p class="mt-6 whitespace-pre-line text-base leading-relaxed text-muted-foreground sm:text-lg {{ $style['center'] ? 'mx-auto max-w-2xl text-center' : 'max-w-2xl' }}">{{ $aboutLead }}</p>
                <div class="mt-8 {{ $style['center'] ? 'text-center' : '' }}">
                    <a href="{{ $photographer->public_url }}/about" class="btn btn-outline">More about {{ Str::before($photographer->name, ' ') ?: $photographer->name }} <x-icon name="arrow-right" /></a>
                </div>
            </div>
        </div>
    </div>
</section>
