{{-- Packages. Vars from public/photographer + $section, $tone --}}
<section id="packages" class="scroll-mt-16 {{ $style['border'] }} {{ $tone }}">
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 {{ $style['pad'] }}">
        @include('public.portfolio.partials.section-heading', ['center' => $style['center']])
        <p class="mt-3 text-sm leading-relaxed text-muted-foreground {{ $style['center'] ? 'text-center' : 'max-w-2xl' }}">Pick the package that fits, then choose an open time that suits you.</p>

        <div class="mt-10 grid gap-5 md:grid-cols-2 lg:grid-cols-3 {{ $style['center'] && $packages->count() < 3 ? 'mx-auto max-w-4xl lg:grid-cols-2' : '' }}">
            @foreach($packages as $pkg)
                <div class="card card-hover flex flex-col p-6 sm:p-7 {{ $template === 'bold' ? 'rounded-2xl' : '' }}" data-reveal style="--reveal-delay: {{ ($loop->index % 3) * 80 }}ms">
                    <div class="flex items-start justify-between gap-3">
                        <h3 class="pf-heading text-2xl text-foreground">{{ $pkg->name }}</h3>
                        @if($pkg->duration_minutes)
                            <span class="badge badge-neutral shrink-0"><x-icon name="clock" /> {{ $pkg->duration_minutes >= 60 && $pkg->duration_minutes % 60 === 0 ? ($pkg->duration_minutes / 60) . ' ' . Str::plural('hr', $pkg->duration_minutes / 60) : $pkg->duration_minutes . ' min' }}</span>
                        @endif
                    </div>

                    <div class="mt-5 flex items-baseline gap-1.5">
                        <span class="text-xs font-medium text-muted-foreground">{{ $pkg->currency }}</span>
                        <span class="font-display text-4xl font-semibold tracking-tight tabular-nums text-foreground">{{ number_format($pkg->price, 0) }}</span>
                    </div>

                    @if($pkg->description)
                        <p class="mt-4 text-[13px] leading-relaxed text-muted-foreground">{{ $pkg->description }}</p>
                    @endif

                    @if(!empty($pkg->deliverables))
                        <ul class="mt-5 space-y-2.5 border-t border-border pt-5 text-[13px] text-foreground">
                            @foreach($pkg->deliverables as $item)
                                <li class="flex items-start gap-2.5">
                                    <x-icon name="check" class="mt-0.5 size-4 text-success" />
                                    <span>{{ $item }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <div class="mt-auto pt-7">
                        <a href="{{ $bookUrl }}?package={{ $pkg->uuid }}" class="btn btn-outline w-full">
                            Book this package <x-icon name="arrow-right" />
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
