{{-- Curated collections. Vars from public/photographer + $section, $tone --}}
@php
    $categories = $galleries->pluck('portfolio_category')->filter()->unique()->values();
    $filterable = $categories->count() > 1;
    $grid = [
        'editorial' => 'grid gap-x-6 gap-y-12 sm:grid-cols-2 lg:grid-cols-3',
        'minimal' => 'grid gap-x-10 gap-y-16 md:grid-cols-2',
        'bold' => 'grid gap-4 sm:grid-cols-2',
        'grid' => 'grid grid-cols-2 gap-1.5 md:grid-cols-3 xl:grid-cols-4',
    ][$template];
@endphp
<section id="work" class="scroll-mt-16 {{ $style['border'] }} {{ $tone }}" x-data="{ category: 'all' }">
    <div class="mx-auto {{ $template === 'grid' ? 'max-w-[1600px]' : 'max-w-7xl' }} px-4 sm:px-6 lg:px-8 {{ $style['pad'] }}">
        <div class="flex flex-col gap-5 {{ $style['center'] ? 'items-center' : 'sm:flex-row sm:items-end sm:justify-between' }}">
            @include('public.portfolio.partials.section-heading', ['center' => $style['center']])
            @if($galleries->isNotEmpty() && !$filterable)
                <p class="text-sm text-muted-foreground">{{ $galleries->count() }} {{ Str::plural('collection', $galleries->count()) }}</p>
            @endif
        </div>

        @if($filterable)
            <div class="mt-8 flex flex-wrap gap-2 {{ $style['center'] ? 'justify-center' : '' }}" role="group" aria-label="Filter collections">
                <button type="button" @click="category = 'all'" :aria-pressed="(category === 'all').toString()"
                        :class="category === 'all' ? 'bg-foreground text-background' : 'bg-muted text-muted-foreground hover:text-foreground'"
                        class="rounded-full px-4 py-1.5 text-[13px] font-medium transition">All</button>
                @foreach($categories as $category)
                    <button type="button" @click="category = @js($category)" :aria-pressed="(category === @js($category)).toString()"
                            :class="category === @js($category) ? 'bg-foreground text-background' : 'bg-muted text-muted-foreground hover:text-foreground'"
                            class="rounded-full px-4 py-1.5 text-[13px] font-medium transition">{{ $category }}</button>
                @endforeach
            </div>
        @endif

        @if($galleries->isEmpty())
            <x-ui.empty-state class="mt-10" icon="images" title="No public collections yet"
                              description="{{ $photographer->name }} hasn't shared any galleries publicly. Check back soon." />
        @else
            <div class="{{ $filterable ? 'mt-8' : 'mt-10' }} {{ $grid }}">
                @foreach($galleries as $gallery)
                    @php
                        $cover = $gallery->getCoverUrl('lg');
                        $coverSrcset = $cover ? $gallery->getCoverSrcset() : null;
                        $featureFirst = $template === 'bold' && $loop->first && $galleries->count() > 1;
                    @endphp
                    <a href="{{ $gallery->public_url }}"
                       @if($filterable) x-show="category === 'all' || category === @js($gallery->portfolio_category)" @endif
                       class="group relative block {{ $featureFirst ? 'sm:col-span-2' : '' }}"
                       data-reveal style="--reveal-delay: {{ ($loop->index % 3) * 80 }}ms">
                        @switch($template)
                            @case('bold')
                            @case('grid')
                                {{-- Title over the image --}}
                                <div class="relative overflow-hidden bg-muted {{ $template === 'bold' ? 'rounded-xl' : '' }} {{ $template === 'grid' ? 'aspect-square' : ($featureFirst ? 'aspect-[4/3] sm:aspect-[21/9]' : 'aspect-[4/3]') }}">
                                    @if($cover)
                                        <img src="{{ $cover }}" @if($coverSrcset) srcset="{{ $coverSrcset }}" sizes="{{ $featureFirst ? '100vw' : ($template === 'grid' ? '(min-width: 1280px) 25vw, (min-width: 768px) 33vw, 50vw' : '(min-width: 640px) 50vw, 100vw') }}" @endif alt="{{ $gallery->title }}" loading="lazy" decoding="async"
                                             class="size-full object-cover transition-transform duration-700 ease-out group-hover:scale-[1.04]">
                                    @else
                                        <div class="flex size-full items-center justify-center bg-gradient-to-br from-muted to-secondary">
                                            <span class="pf-heading text-6xl leading-none text-foreground/15">{{ mb_strtoupper(mb_substr($gallery->title, 0, 1)) }}</span>
                                        </div>
                                    @endif
                                    <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/10 to-transparent {{ $template === 'grid' ? 'opacity-100 sm:opacity-0 sm:transition-opacity sm:duration-300 sm:group-hover:opacity-100' : '' }}"></div>
                                    <div class="absolute inset-x-0 bottom-0 p-4 text-white sm:p-6 {{ $template === 'grid' ? 'sm:translate-y-2 sm:opacity-0 sm:transition-all sm:duration-300 sm:group-hover:translate-y-0 sm:group-hover:opacity-100' : '' }}">
                                        <h3 class="pf-heading truncate {{ $template === 'bold' ? ($featureFirst ? 'text-3xl sm:text-5xl' : 'text-2xl sm:text-3xl') : 'text-lg sm:text-xl' }}">{{ $gallery->title }}</h3>
                                        <p class="mt-1 text-[11px] uppercase tracking-[0.2em] text-white/75">
                                            {{ $gallery->portfolio_category ?? ($gallery->event_date ? \Carbon\Carbon::parse($gallery->event_date)->format('F Y') : $gallery->photo_count . ' ' . Str::plural('photo', $gallery->photo_count)) }}
                                        </p>
                                    </div>
                                </div>
                                @break

                            @default
                                {{-- Editorial and Minimal: caption under the image --}}
                                <div class="relative overflow-hidden bg-muted {{ $template === 'minimal' ? 'aspect-[3/2] rounded-lg' : 'aspect-[4/5] rounded-xl' }}">
                                    @if($cover)
                                        <img src="{{ $cover }}" @if($coverSrcset) srcset="{{ $coverSrcset }}" sizes="{{ $template === 'minimal' ? '(min-width: 768px) 50vw, 100vw' : '(min-width: 1024px) 33vw, (min-width: 640px) 50vw, 100vw' }}" @endif alt="{{ $gallery->title }}" loading="lazy" decoding="async"
                                             class="size-full object-cover transition-transform duration-700 ease-out group-hover:scale-[1.04]">
                                        <div class="absolute inset-0 bg-black/0 transition-colors duration-500 group-hover:bg-black/15"></div>
                                    @else
                                        <div class="flex size-full flex-col items-center justify-center gap-3 bg-gradient-to-br from-muted to-secondary p-8 text-center">
                                            <span class="pf-heading text-6xl leading-none text-foreground/15">{{ mb_strtoupper(mb_substr($gallery->title, 0, 1)) }}</span>
                                            <span class="text-[11px] uppercase tracking-[0.24em] text-subtle">Cover coming soon</span>
                                        </div>
                                    @endif
                                    @if($template === 'editorial')
                                        <span class="absolute bottom-4 right-4 inline-flex translate-y-1 items-center gap-1.5 rounded-full bg-white/90 px-3 py-1.5 text-xs font-medium text-black opacity-0 shadow-sm backdrop-blur transition-all duration-300 group-hover:translate-y-0 group-hover:opacity-100">
                                            View gallery <x-icon name="arrow-up-right" class="size-3.5" />
                                        </span>
                                    @endif
                                </div>
                                @if($template === 'minimal')
                                    <div class="mt-4 text-center">
                                        <h3 class="pf-heading text-2xl text-foreground">{{ $gallery->title }}</h3>
                                        <p class="mt-1 text-[11px] uppercase tracking-[0.24em] text-muted-foreground">
                                            {{ collect([$gallery->portfolio_category, $gallery->event_date ? \Carbon\Carbon::parse($gallery->event_date)->format('F Y') : null])->filter()->implode(' · ') ?: $gallery->photo_count . ' ' . Str::plural('photo', $gallery->photo_count) }}
                                        </p>
                                    </div>
                                @else
                                    <div class="mt-4 flex items-baseline justify-between gap-4">
                                        <h3 class="pf-heading min-w-0 truncate text-2xl text-foreground">{{ $gallery->title }}</h3>
                                        <span class="shrink-0 text-xs tabular-nums text-muted-foreground">{{ $gallery->photo_count }} {{ Str::plural('photo', $gallery->photo_count) }}</span>
                                    </div>
                                    @if($gallery->portfolio_category || $gallery->event_date)
                                        <p class="mt-1 text-[11px] uppercase tracking-[0.2em] text-muted-foreground">
                                            {{ collect([$gallery->portfolio_category, $gallery->event_date ? \Carbon\Carbon::parse($gallery->event_date)->format('F Y') : null])->filter()->implode(' · ') }}
                                        </p>
                                    @endif
                                @endif
                        @endswitch
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</section>
