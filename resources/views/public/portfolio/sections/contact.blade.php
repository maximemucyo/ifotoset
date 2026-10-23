{{-- Closing call to action: book, message or follow. Vars from public/photographer + $section, $tone --}}
@php($socialLinks = $portfolio->socialLinks())
<section id="contact" class="scroll-mt-16 {{ $style['border'] }} {{ $tone }}">
    <div class="mx-auto flex max-w-3xl flex-col items-center px-4 text-center sm:px-6 lg:px-8 {{ $style['pad'] }}">
        <p class="text-[11px] font-medium uppercase tracking-[0.28em] text-muted-foreground">{{ $section['eyebrow'] }}</p>
        <h2 class="pf-heading mt-3 text-4xl leading-[1.05] text-foreground sm:text-5xl">{{ $section['heading'] }}</h2>
        <p class="mt-5 max-w-md text-sm leading-relaxed text-muted-foreground">
            @if($hasPackages)
                Choose a package, pick an open time and leave your details. {{ $photographer->name }} will confirm by email.
            @else
                Tell {{ $photographer->name }} about your plans and you'll hear back by email.
            @endif
        </p>
        <div class="mt-8 flex flex-wrap justify-center gap-3">
            @if($hasPackages)
                <a href="{{ $bookUrl }}" class="btn btn-primary btn-lg">Book a session <x-icon name="arrow-right" /></a>
                <a href="{{ $photographer->public_url }}/contact" class="btn btn-outline btn-lg">Send a message</a>
            @else
                <a href="{{ $photographer->public_url }}/contact" class="btn btn-primary btn-lg">Get in touch <x-icon name="arrow-right" /></a>
            @endif
        </div>
        @if($socialLinks)
            <div class="mt-8 flex items-center gap-2">
                @foreach($socialLinks as $social)
                    <a href="{{ $social['url'] }}" target="_blank" rel="noopener noreferrer me" aria-label="{{ $social['label'] }}"
                       class="flex size-10 items-center justify-center rounded-full border border-border text-muted-foreground transition hover:border-border-strong hover:text-foreground">
                        <x-icon :name="$social['icon']" class="size-4" />
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</section>
