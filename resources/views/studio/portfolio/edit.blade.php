@extends('layouts.app', ['title' => 'Portfolio · ifotoset'])

@use('App\Support\Portfolio\PortfolioSettings')

@php
    $publicUrl = $user->username ? $user->public_url : null;
    $tabs = [
        ['id' => 'design', 'label' => 'Design', 'icon' => 'palette', 'errors' => ['template', 'font', 'brand_color']],
        ['id' => 'branding', 'label' => 'Branding', 'icon' => 'sparkles', 'errors' => ['role_label', 'hero_headline', 'hero_text']],
        ['id' => 'sections', 'label' => 'Sections', 'icon' => 'layers', 'errors' => ['sections']],
        ['id' => 'collections', 'label' => 'Collections', 'icon' => 'images', 'errors' => ['galleries']],
        ['id' => 'about', 'label' => 'About & reviews', 'icon' => 'book-open', 'errors' => ['about_body', 'testimonials']],
        ['id' => 'social', 'label' => 'Social & contact', 'icon' => 'at-sign', 'errors' => ['instagram', 'tiktok', 'facebook', 'whatsapp']],
        ['id' => 'seo', 'label' => 'SEO & sharing', 'icon' => 'search', 'errors' => ['seo_title', 'seo_description']],
    ];
    $initialTab = collect($tabs)->first(fn ($t) => collect($errors->keys())->contains(fn ($key) => collect($t['errors'])->contains(fn ($prefix) => str_starts_with($key, $prefix))))['id'] ?? 'design';

    $swatches = ['#cc4019', '#17110e', '#0f766e', '#1d4ed8', '#7c3aed', '#be185d', '#b45309', '#15803d'];
    $brandColor = old('brand_color', $portfolio->brandColor() ?? '#cc4019');
    $useBrandColor = (bool) old('use_brand_color', $portfolio->brandColor() !== null);

    $sectionRows = old('sections') ?? collect($portfolio->sections())->map(fn ($s) => [
        'id' => $s['id'], 'visible' => $s['visible'], 'eyebrow' => $s['custom_eyebrow'], 'heading' => $s['custom_heading'],
    ])->all();
    $sectionRows = collect($sectionRows)->filter(fn ($s) => isset(PortfolioSettings::SECTIONS[$s['id'] ?? null]))->map(fn ($s) => [
        'id' => $s['id'],
        'visible' => (bool) ($s['visible'] ?? false),
        'eyebrow' => $s['eyebrow'] ?? '',
        'heading' => $s['heading'] ?? '',
        'label' => PortfolioSettings::SECTIONS[$s['id']]['label'],
        'defaultEyebrow' => PortfolioSettings::SECTIONS[$s['id']]['eyebrow'],
        'defaultHeading' => PortfolioSettings::SECTIONS[$s['id']]['heading'],
    ])->values();

    $testimonialRows = collect(old('testimonials', $portfolio->testimonials()))
        ->map(fn ($t) => ['quote' => $t['quote'] ?? '', 'author' => $t['author'] ?? '', 'context' => $t['context'] ?? ''])
        ->values();

    $galleryRows = $galleries->map(fn ($g) => [
        'uuid' => (string) $g->uuid,
        'title' => $g->title,
        'thumb' => $g->getCoverUrl('sm'),
        'photos' => $g->photo_count,
        'show' => (bool) $g->show_on_profile,
        'category' => $g->portfolio_category ?? '',
        'editUrl' => route('studio.galleries.show', $g->uuid),
    ])->values();

    $imageEndpoints = fn (string $kind) => [
        'requestUrl' => route('studio.portfolio.images.request', $kind),
        'confirmUrl' => route('studio.portfolio.images.confirm', $kind),
        'removeUrl' => route('studio.portfolio.images.destroy', $kind),
    ];
@endphp

@push('styles')
    @vite('resources/css/portfolio.css')
@endpush

@section('breadcrumbs')
    <span>Website</span>
    <x-icon name="chevron-right" class="size-3.5 text-subtle" />
    <span class="font-medium text-foreground">Portfolio</span>
@endsection

@section('content')
<div x-data="{
        tab: '{{ $initialTab }}',
        tabs: @js(array_column($tabs, 'id')),
        init() {
            const h = window.location.hash.replace('#', '');
            if (this.tabs.includes(h) && '{{ $initialTab }}' === 'design') this.tab = h;
        },
        go(id) {
            this.tab = id;
            history.replaceState(null, '', '#' + id);
        }
     }">
    <x-ui.page-header title="Portfolio" description="Choose a template, add your branding and decide what clients see on your public site.">
        @if($publicUrl)
            <x-slot:actions>
                <button type="button" class="btn btn-outline btn-sm" @click="$clipboard(@js($publicUrl))"><x-icon name="copy" /> Copy link</button>
                <a href="{{ $publicUrl }}" target="_blank" rel="noopener" class="btn btn-primary btn-sm"><x-icon name="external-link" /> View site</a>
            </x-slot:actions>
        @endif
    </x-ui.page-header>

    @if(!$user->profile_public)
        <div class="mb-6 flex flex-col gap-3 rounded-xl border border-warning/30 bg-warning/10 p-4 text-sm text-foreground sm:flex-row sm:items-center sm:justify-between">
            <span class="flex items-start gap-2.5"><x-icon name="eye-off" class="mt-0.5 size-4 text-warning" /> Your portfolio is hidden. Only you can see it while you're signed in.</span>
            <a href="{{ route('studio.settings.index') }}#public" class="btn btn-outline btn-sm shrink-0">Visibility settings</a>
        </div>
    @endif

    <div class="grid grid-cols-[minmax(0,1fr)] gap-6 lg:grid-cols-[220px_minmax(0,1fr)] lg:gap-10">
        {{-- ============ Tab nav ============ --}}
        <nav aria-label="Portfolio sections" class="min-w-0 lg:sticky lg:top-6 lg:self-start">
            <div class="-mx-4 flex gap-1 overflow-x-auto px-4 pb-1 lg:hidden" role="tablist">
                @foreach($tabs as $t)
                    <button type="button" role="tab" @click="go('{{ $t['id'] }}')"
                            :aria-selected="(tab === '{{ $t['id'] }}').toString()"
                            :class="tab === '{{ $t['id'] }}' ? 'bg-card text-foreground shadow-xs ring-1 ring-border' : 'text-muted-foreground hover:text-foreground'"
                            class="inline-flex h-8 shrink-0 items-center gap-1.5 rounded-lg px-3 text-[13px] font-medium transition">
                        <x-icon :name="$t['icon']" class="size-3.5" /> {{ $t['label'] }}
                    </button>
                @endforeach
            </div>
            <div class="hidden space-y-0.5 lg:block" role="tablist" aria-orientation="vertical">
                @foreach($tabs as $t)
                    <button type="button" role="tab" @click="go('{{ $t['id'] }}')"
                            :aria-selected="(tab === '{{ $t['id'] }}').toString()"
                            :class="tab === '{{ $t['id'] }}' ? 'bg-muted text-foreground' : 'text-muted-foreground hover:bg-muted/60 hover:text-foreground'"
                            class="group flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-left text-[13px] font-medium transition">
                        <x-icon :name="$t['icon']" class="size-4" />
                        <span class="flex-1">{{ $t['label'] }}</span>
                        <span x-show="tab === '{{ $t['id'] }}'" class="size-1.5 rounded-full bg-brand"></span>
                    </button>
                @endforeach
                <div class="my-3 h-px bg-border"></div>
                <a href="{{ route('studio.settings.index') }}" class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-[13px] font-medium text-muted-foreground transition hover:bg-muted/60 hover:text-foreground">
                    <x-icon name="user" class="size-4" />
                    <span class="flex-1">Profile &amp; address</span>
                    <x-icon name="arrow-up-right" class="size-3.5 opacity-60" />
                </a>
            </div>
        </nav>

        <div class="min-w-0 space-y-6">

            {{-- ============ Design ============ --}}
            <section x-show="tab === 'design'" class="card" aria-labelledby="design-heading">
                <form method="POST" action="{{ route('studio.portfolio.update', 'design') }}"
                      x-data="{ template: @js(old('template', $portfolio->storedTemplate())), font: @js(old('font', $portfolio->fontKey())), color: @js($brandColor), useColor: @js($useBrandColor) }">
                    @csrf
                    <div class="card-header">
                        <div>
                            <h2 id="design-heading" class="card-title">Design</h2>
                            <p class="card-description">Every template shows the same content, so you can switch any time.</p>
                        </div>
                    </div>
                    <div class="card-body space-y-8">
                        <fieldset>
                            <legend class="label">Template</legend>
                            <div class="grid gap-3 sm:grid-cols-2">
                                @foreach(PortfolioSettings::TEMPLATES as $key => $meta)
                                    @php($locked = $meta['premium'] && !$portfolio->premiumAllowed())
                                    <label class="relative flex flex-col rounded-xl border p-3 transition {{ $locked ? 'cursor-not-allowed opacity-70' : 'cursor-pointer hover:border-border-strong' }}"
                                           :class="template === '{{ $key }}' ? 'border-brand ring-2 ring-brand/20' : 'border-border'">
                                        <input type="radio" name="template" value="{{ $key }}" x-model="template" class="sr-only" @disabled($locked)>
                                        @include('studio.portfolio.partials.template-thumb', ['template' => $key])
                                        <span class="mt-3 flex items-center justify-between gap-2">
                                            <span class="text-sm font-medium text-foreground">{{ $meta['name'] }}</span>
                                            @if($locked)
                                                <x-ui.badge variant="neutral"><x-icon name="lock" class="size-3" /> Paid plans</x-ui.badge>
                                            @elseif($meta['premium'])
                                                <x-ui.badge variant="neutral"><x-icon name="crown" class="size-3" /> Premium</x-ui.badge>
                                            @endif
                                        </span>
                                        <span class="mt-0.5 text-[13px] text-muted-foreground">{{ $meta['description'] }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @error('template') <p class="error-text">{{ $message }}</p> @enderror
                            @if(!$portfolio->premiumAllowed())
                                <p class="help">Bold and Grid come with every paid plan.@can('studio.billing') <a href="{{ route('studio.billing.index') }}" class="link-brand">See plans</a>@endcan</p>
                            @endif
                        </fieldset>

                        <fieldset>
                            <legend class="label">Headline font</legend>
                            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                @foreach(PortfolioSettings::FONTS as $key => $meta)
                                    <label class="flex cursor-pointer flex-col rounded-xl border p-4 transition hover:border-border-strong"
                                           :class="font === '{{ $key }}' ? 'border-brand ring-2 ring-brand/20' : 'border-border'">
                                        <input type="radio" name="font" value="{{ $key }}" x-model="font" class="sr-only">
                                        <span class="text-3xl leading-none text-foreground" style="font-family: {{ $meta['family'] }}; font-weight: {{ $meta['weight'] }}; letter-spacing: {{ $meta['tracking'] }}">Aa</span>
                                        <span class="mt-3 text-sm font-medium text-foreground">{{ $meta['name'] }}</span>
                                        <span class="text-xs text-muted-foreground">{{ $meta['sample'] }} + Inter</span>
                                    </label>
                                @endforeach
                            </div>
                            @error('font') <p class="error-text">{{ $message }}</p> @enderror
                        </fieldset>

                        <fieldset>
                            <legend class="sr-only">Brand colour</legend>
                            <label class="flex cursor-pointer items-start justify-between gap-4">
                                <span>
                                    <span class="block text-sm font-medium text-foreground">Use my brand colour</span>
                                    <span class="mt-0.5 block text-[13px] text-muted-foreground">Colours buttons and accents on your portfolio and booking pages. Off uses the ifotoset orange.</span>
                                </span>
                                <span class="switch mt-1">
                                    <input type="checkbox" name="use_brand_color" value="1" class="sr-only" x-model="useColor">
                                    <span></span>
                                </span>
                            </label>
                            <div x-show="useColor" x-collapse x-cloak class="mt-4">
                                <div class="flex flex-wrap items-center gap-2">
                                    @foreach($swatches as $swatch)
                                        <button type="button" @click="color = '{{ $swatch }}'" aria-label="Use {{ $swatch }}"
                                                class="size-8 rounded-full ring-offset-2 ring-offset-card transition"
                                                :class="color.toLowerCase() === '{{ $swatch }}' ? 'ring-2 ring-foreground' : 'ring-1 ring-border hover:scale-110'"
                                                style="background-color: {{ $swatch }}"></button>
                                    @endforeach
                                    <span class="mx-1 h-6 w-px bg-border"></span>
                                    <label class="relative size-8 cursor-pointer overflow-hidden rounded-full ring-1 ring-border" title="Pick any colour">
                                        <input type="color" :value="color" @input="color = $event.target.value" class="absolute -inset-2 size-12 cursor-pointer border-0 p-0" aria-label="Pick any colour">
                                    </label>
                                    <input type="text" name="brand_color" x-model="color" maxlength="7" spellcheck="false"
                                           class="input h-8 w-28 font-mono text-[13px] {{ $errors->has('brand_color') ? 'input-error' : '' }}" aria-label="Hex colour">
                                </div>
                                @error('brand_color') <p class="error-text">{{ $message }}</p> @enderror
                                <div class="mt-4 flex items-center gap-3">
                                    <span class="btn btn-sm pointer-events-none" :style="`background-color: ${color}; color: ${contrastText(color)}`">Book a session</span>
                                    <span class="text-xs text-muted-foreground">Preview</span>
                                </div>
                            </div>
                        </fieldset>
                    </div>
                    <div class="card-footer flex justify-end">
                        <button type="submit" class="btn btn-primary">Save design</button>
                    </div>
                </form>
            </section>

            {{-- ============ Branding ============ --}}
            <section x-show="tab === 'branding'" x-cloak class="card" aria-labelledby="branding-heading">
                <div class="card-header">
                    <div>
                        <h2 id="branding-heading" class="card-title">Branding</h2>
                        <p class="card-description">Your logo, cover image and the words at the top of your portfolio.</p>
                    </div>
                </div>

                <div class="grid gap-0 border-b border-border sm:grid-cols-2">
                    @include('studio.portfolio.partials.image-field', [
                        'kind' => 'logo',
                        'title' => 'Logo',
                        'hint' => 'Shown in the header instead of your name. PNG with a transparent background works best.',
                        'current' => $user->logo_url,
                        'previewClass' => 'h-20 w-full object-contain',
                        'boxClass' => 'flex h-28 items-center justify-center rounded-xl border border-dashed border-border bg-[repeating-conic-gradient(var(--muted)_0_25%,transparent_0_50%)] bg-[length:16px_16px] p-4',
                        'maxMb' => 10,
                        'endpoints' => $imageEndpoints('logo'),
                    ])
                    @include('studio.portfolio.partials.image-field', [
                        'kind' => 'cover',
                        'title' => 'Cover image',
                        'hint' => 'The large image at the top of your portfolio. Without one we use your first collection\'s cover.',
                        'current' => $user->cover_url,
                        'previewClass' => 'size-full object-cover',
                        'boxClass' => 'h-28 overflow-hidden rounded-xl border border-dashed border-border bg-muted',
                        'maxMb' => 25,
                        'endpoints' => $imageEndpoints('cover'),
                    ])
                </div>

                <form method="POST" action="{{ route('studio.portfolio.update', 'branding') }}">
                    @csrf
                    <div class="card-body space-y-5">
                        <div>
                            <x-ui.input label="What you do" name="role_label" list="role-suggestions" maxlength="40" :placeholder="PortfolioSettings::DEFAULT_ROLE"
                                        :value="old('role_label', $portfolio->get('role_label'))" :error="$errors->first('role_label')"
                                        hint="Shown above your name, e.g. Photographer, Videographer or Studio." />
                            <datalist id="role-suggestions">
                                @foreach(PortfolioSettings::ROLE_SUGGESTIONS as $role)
                                    <option value="{{ $role }}"></option>
                                @endforeach
                            </datalist>
                        </div>
                        <x-ui.input label="Headline" name="hero_headline" maxlength="120" :placeholder="$user->name"
                                    :value="old('hero_headline', $portfolio->get('hero_headline'))" :error="$errors->first('hero_headline')"
                                    hint="The big line at the top. Leave empty to show your name." />
                        <x-ui.textarea label="Introduction" name="hero_text" rows="3" maxlength="400"
                                       :placeholder="$user->bio ?: 'One or two sentences about your style and the sessions you love shooting.'"
                                       :value="old('hero_text', $portfolio->get('hero_text'))" :error="$errors->first('hero_text')"
                                       hint="Leave empty to use the bio from your profile." />
                    </div>
                    <div class="card-footer flex items-center justify-end gap-3">
                        <p class="mr-auto hidden text-xs text-muted-foreground sm:block">Your profile photo is set in <a href="{{ route('studio.settings.index') }}" class="link">Settings</a>.</p>
                        <button type="submit" class="btn btn-primary">Save branding</button>
                    </div>
                </form>
            </section>

            {{-- ============ Sections ============ --}}
            <section x-show="tab === 'sections'" x-cloak class="card" aria-labelledby="sections-heading">
                <form method="POST" action="{{ route('studio.portfolio.update', 'sections') }}" x-data="sortableList(@js($sectionRows))">
                    @csrf
                    <div class="card-header">
                        <div>
                            <h2 id="sections-heading" class="card-title">Home page sections</h2>
                            <p class="card-description">Drag to reorder, hide what you don't need and rename the headings. Your hero always comes first.</p>
                        </div>
                    </div>
                    @error('sections') <p class="error-text px-5 pt-4">{{ $message }}</p> @enderror
                    <ol class="divide-y divide-border border-y border-border">
                        <template x-for="(item, index) in items" :key="item.id">
                            <li class="flex gap-3 px-5 py-4 transition-colors" :class="dragging === index ? 'bg-muted/60' : ''"
                                draggable="true" @dragstart="start(index, $event)" @dragover.prevent="over(index)" @drop.prevent="drop()" @dragend="drop()">
                                <input type="hidden" :name="`sections[${index}][id]`" :value="item.id">
                                <div class="flex shrink-0 flex-col items-center gap-1 pt-1">
                                    <span class="cursor-grab text-subtle active:cursor-grabbing" aria-hidden="true"><x-icon name="grip-vertical" /></span>
                                    <button type="button" class="btn btn-ghost btn-sm !size-7 !p-0" @click="move(index, -1)" :disabled="index === 0" :aria-label="`Move ${item.label} up`"><x-icon name="chevron-up" class="size-3.5" /></button>
                                    <button type="button" class="btn btn-ghost btn-sm !size-7 !p-0" @click="move(index, 1)" :disabled="index === items.length - 1" :aria-label="`Move ${item.label} down`"><x-icon name="chevron-down" class="size-3.5" /></button>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="text-sm font-medium text-foreground" x-text="item.label" :class="item.visible ? '' : 'text-muted-foreground line-through'"></span>
                                        <label class="flex cursor-pointer items-center gap-2 text-xs text-muted-foreground">
                                            <span x-text="item.visible ? 'Shown' : 'Hidden'"></span>
                                            <span class="switch">
                                                <input type="checkbox" :name="`sections[${index}][visible]`" value="1" class="sr-only" x-model="item.visible">
                                                <span></span>
                                            </span>
                                        </label>
                                    </div>
                                    {{-- x-show (not x-if) so hidden sections keep their custom headings --}}
                                    <div x-show="item.visible" class="mt-3 grid gap-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]">
                                        <input type="text" class="input" maxlength="40" :name="`sections[${index}][eyebrow]`" x-model="item.eyebrow" :placeholder="item.defaultEyebrow" :aria-label="`${item.label} label`">
                                        <input type="text" class="input" maxlength="120" :name="`sections[${index}][heading]`" x-model="item.heading" :placeholder="item.defaultHeading" :aria-label="`${item.label} heading`">
                                    </div>
                                </div>
                            </li>
                        </template>
                    </ol>
                    <div class="card-footer flex items-center justify-end gap-3">
                        <p class="mr-auto hidden text-xs text-muted-foreground sm:block">Empty sections (no packages, about text or testimonials) are skipped automatically.</p>
                        <button type="submit" class="btn btn-primary">Save sections</button>
                    </div>
                </form>
            </section>

            {{-- ============ Collections ============ --}}
            <section x-show="tab === 'collections'" x-cloak class="card" aria-labelledby="collections-heading">
                <form method="POST" action="{{ route('studio.portfolio.collections') }}" x-data="sortableList(@js($galleryRows))">
                    @csrf
                    <div class="card-header">
                        <div>
                            <h2 id="collections-heading" class="card-title">Collections</h2>
                            <p class="card-description">Pick which public galleries appear in your portfolio, drag them into order and group them by category.</p>
                        </div>
                    </div>
                    @if($galleryRows->isEmpty())
                        <div class="card-body">
                            <x-ui.empty-state icon="images" title="No public galleries yet"
                                              description="Galleries set to Public can be featured on your portfolio. Private and PIN-protected galleries never appear." />
                        </div>
                    @else
                        @if($errors->has('galleries') || collect($errors->keys())->contains(fn ($k) => str_starts_with($k, 'galleries.')))
                            <p class="error-text px-5 pt-4">{{ $errors->first('galleries') ?: collect($errors->getMessages())->first(fn ($m, $k) => str_starts_with($k, 'galleries.'))[0] }}</p>
                        @endif
                        <ol class="divide-y divide-border border-y border-border">
                            <template x-for="(item, index) in items" :key="item.uuid">
                                <li class="flex items-center gap-3 px-4 py-3 transition-colors sm:px-5" :class="dragging === index ? 'bg-muted/60' : ''"
                                    draggable="true" @dragstart="start(index, $event)" @dragover.prevent="over(index)" @drop.prevent="drop()" @dragend="drop()">
                                    <input type="hidden" :name="`galleries[${index}][uuid]`" :value="item.uuid">
                                    <div class="flex shrink-0 items-center gap-0.5">
                                        <span class="hidden cursor-grab text-subtle active:cursor-grabbing sm:block" aria-hidden="true"><x-icon name="grip-vertical" /></span>
                                        <div class="flex flex-col">
                                            <button type="button" class="btn btn-ghost btn-sm !h-6 !w-7 !p-0" @click="move(index, -1)" :disabled="index === 0" :aria-label="`Move ${item.title} up`"><x-icon name="chevron-up" class="size-3.5" /></button>
                                            <button type="button" class="btn btn-ghost btn-sm !h-6 !w-7 !p-0" @click="move(index, 1)" :disabled="index === items.length - 1" :aria-label="`Move ${item.title} down`"><x-icon name="chevron-down" class="size-3.5" /></button>
                                        </div>
                                    </div>
                                    <div class="size-14 shrink-0 overflow-hidden rounded-lg bg-muted" :class="item.show ? '' : 'opacity-50'">
                                        <template x-if="item.thumb"><img :src="item.thumb" alt="" class="size-full object-cover" loading="lazy"></template>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-medium" :class="item.show ? 'text-foreground' : 'text-muted-foreground'" x-text="item.title"></p>
                                        <p class="text-xs text-muted-foreground">
                                            <span x-text="`${item.photos} ${item.photos === 1 ? 'photo' : 'photos'}`"></span> ·
                                            <a :href="item.editUrl" class="link">Change cover</a>
                                        </p>
                                    </div>
                                    <input type="text" class="input hidden h-9 w-40 text-[13px] md:block" maxlength="60" list="category-suggestions" placeholder="Category"
                                           :name="`galleries[${index}][category]`" x-model="item.category" :aria-label="`Category for ${item.title}`">
                                    <label class="flex shrink-0 cursor-pointer items-center gap-2 text-xs text-muted-foreground">
                                        <span class="hidden sm:inline" x-text="item.show ? 'Shown' : 'Hidden'"></span>
                                        <span class="switch">
                                            <input type="checkbox" :name="`galleries[${index}][show]`" value="1" class="sr-only" x-model="item.show" :aria-label="`Show ${item.title} on portfolio`">
                                            <span></span>
                                        </span>
                                    </label>
                                </li>
                            </template>
                        </ol>
                        <datalist id="category-suggestions">
                            @foreach($categories->merge(['Weddings', 'Portraits', 'Events', 'Fashion', 'Commercial', 'Family'])->unique() as $category)
                                <option value="{{ $category }}"></option>
                            @endforeach
                        </datalist>
                        <p class="px-5 pt-3 text-xs text-muted-foreground md:hidden">Categories can be set on a larger screen.</p>
                    @endif
                    <div class="card-footer flex items-center justify-end gap-3">
                        <p class="mr-auto hidden text-xs text-muted-foreground sm:block">With two or more categories, visitors can filter your work. New galleries appear at the end until you reorder.</p>
                        @if($galleryRows->isNotEmpty())
                            <button type="submit" class="btn btn-primary">Save collections</button>
                        @endif
                    </div>
                </form>
            </section>

            {{-- ============ About & testimonials ============ --}}
            <section x-show="tab === 'about'" x-cloak class="card" aria-labelledby="about-heading">
                <form method="POST" action="{{ route('studio.portfolio.update', 'about') }}"
                      x-data="{ testimonials: @js($testimonialRows), max: {{ PortfolioSettings::MAX_TESTIMONIALS }}, count: {{ mb_strlen((string) old('about_body', $portfolio->get('about_body'))) }} }">
                    @csrf
                    <div class="card-header">
                        <div>
                            <h2 id="about-heading" class="card-title">About page</h2>
                            <p class="card-description">Your story, shown on the About page and as a short section on your home page.</p>
                        </div>
                    </div>
                    <div class="card-body space-y-8">
                        <div>
                            <div class="mb-1.5 flex items-center justify-between">
                                <label for="about_body" class="label !mb-0">About you</label>
                                <span class="text-xs tabular-nums text-muted-foreground"><span x-text="count"></span> / 5000</span>
                            </div>
                            <textarea name="about_body" id="about_body" rows="10" maxlength="5000" @input="count = $event.target.value.length"
                                      class="textarea {{ $errors->has('about_body') ? 'input-error' : '' }}"
                                      placeholder="How you got into photography, what a session with you feels like, where you work and what clients can expect. Leave a blank line between paragraphs.">{{ old('about_body', $portfolio->get('about_body')) }}</textarea>
                            @error('about_body') <p class="error-text">{{ $message }}</p> @else <p class="help">Leave empty to show your profile bio on the About page and skip the home page section.</p> @enderror
                        </div>

                        <div>
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="label !mb-0">Testimonials</p>
                                    <p class="text-[13px] text-muted-foreground">Up to {{ PortfolioSettings::MAX_TESTIMONIALS }} kind words from past clients.</p>
                                </div>
                                <button type="button" class="btn btn-outline btn-sm" @click="testimonials.push({ quote: '', author: '', context: '' })" :disabled="testimonials.length >= max">
                                    <x-icon name="plus" /> Add
                                </button>
                            </div>
                            @if($errors->has('testimonials') || collect($errors->keys())->contains(fn ($k) => str_starts_with($k, 'testimonials.')))
                                <p class="error-text">{{ $errors->first('testimonials') ?: collect($errors->getMessages())->first(fn ($m, $k) => str_starts_with($k, 'testimonials.'))[0] }}</p>
                            @endif
                            <div class="mt-4 space-y-3">
                                <template x-for="(t, index) in testimonials" :key="index">
                                    <div class="rounded-xl border border-border p-4">
                                        <div class="flex items-start gap-3">
                                            <x-icon name="quote" class="mt-2 size-4 shrink-0 text-subtle" />
                                            <div class="min-w-0 flex-1 space-y-3">
                                                <textarea class="textarea" rows="3" maxlength="600" :name="`testimonials[${index}][quote]`" x-model="t.quote" placeholder="What did they say about working with you?" aria-label="Quote"></textarea>
                                                <div class="grid gap-3 sm:grid-cols-2">
                                                    <input type="text" class="input" maxlength="80" :name="`testimonials[${index}][author]`" x-model="t.author" placeholder="Client name" aria-label="Client name">
                                                    <input type="text" class="input" maxlength="80" :name="`testimonials[${index}][context]`" x-model="t.context" placeholder="Wedding, March 2026 (optional)" aria-label="Context">
                                                </div>
                                            </div>
                                            <button type="button" class="btn btn-ghost btn-sm !size-8 !p-0 text-muted-foreground" @click="testimonials.splice(index, 1)" aria-label="Remove testimonial"><x-icon name="trash-2" /></button>
                                        </div>
                                    </div>
                                </template>
                                <p x-show="testimonials.length === 0" class="rounded-xl border border-dashed border-border p-6 text-center text-[13px] text-muted-foreground">No testimonials yet. The section stays hidden until you add one.</p>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer flex justify-end">
                        <button type="submit" class="btn btn-primary">Save about page</button>
                    </div>
                </form>
            </section>

            {{-- ============ Social & contact ============ --}}
            <section x-show="tab === 'social'" x-cloak class="card" aria-labelledby="social-heading">
                <form method="POST" action="{{ route('studio.portfolio.update', 'social') }}">
                    @csrf
                    <div class="card-header">
                        <div>
                            <h2 id="social-heading" class="card-title">Social & contact</h2>
                            <p class="card-description">Linked from your header, footer, About and Contact pages.</p>
                        </div>
                    </div>
                    <div class="card-body space-y-5">
                        <div class="grid gap-5 sm:grid-cols-2">
                            <x-ui.input label="Instagram" name="instagram" icon="instagram" placeholder="@yourstudio"
                                        :value="old('instagram', $portfolio->socialHandle('instagram'))" :error="$errors->first('instagram')" />
                            <x-ui.input label="TikTok" name="tiktok" icon="tiktok" placeholder="@yourstudio"
                                        :value="old('tiktok', $portfolio->socialHandle('tiktok'))" :error="$errors->first('tiktok')" />
                            <x-ui.input label="Facebook" name="facebook" icon="facebook" placeholder="facebook.com/yourstudio"
                                        :value="old('facebook', $portfolio->socialHandle('facebook'))" :error="$errors->first('facebook')" />
                            <x-ui.input label="WhatsApp number" name="whatsapp" type="tel" icon="whatsapp" placeholder="+250 788 000 000"
                                        :value="old('whatsapp', ($wa = $portfolio->socialHandle('whatsapp')) ? '+' . $wa : null)" :error="$errors->first('whatsapp')"
                                        hint="Adds a click-to-chat button. Include your country code." />
                        </div>
                        <label class="flex cursor-pointer items-start justify-between gap-4 rounded-xl border border-border p-4">
                            <span class="flex min-w-0 items-start gap-3">
                                <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground"><x-icon name="mail" class="size-4" /></span>
                                <span>
                                    <span class="block text-sm font-medium text-foreground">Contact form</span>
                                    <span class="mt-0.5 block text-[13px] text-muted-foreground">Visitors can message you from your Contact page. Messages arrive at {{ $user->email }} and replies go straight to them.</span>
                                </span>
                            </span>
                            <span class="switch mt-1">
                                <input type="checkbox" name="contact_form" value="1" class="sr-only" @checked(old('contact_form', $portfolio->contactFormEnabled()))>
                                <span></span>
                            </span>
                        </label>
                    </div>
                    <div class="card-footer flex justify-end">
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </section>

            {{-- ============ SEO & sharing ============ --}}
            <section x-show="tab === 'seo'" x-cloak class="card" aria-labelledby="seo-heading">
                <form method="POST" action="{{ route('studio.portfolio.update', 'seo') }}"
                      x-data="{ title: @js(old('seo_title', $portfolio->get('seo_title')) ?? ''), description: @js(old('seo_description', $portfolio->get('seo_description')) ?? ''), defaultTitle: @js($defaultSeo->title), defaultDescription: @js($defaultSeo->description) }">
                    @csrf
                    <div class="card-header">
                        <div>
                            <h2 id="seo-heading" class="card-title">Search & sharing</h2>
                            <p class="card-description">How your portfolio looks in Google results and when the link is shared on WhatsApp or Instagram.</p>
                        </div>
                    </div>
                    <div class="card-body space-y-5">
                        <div>
                            <div class="mb-1.5 flex items-center justify-between">
                                <label for="seo_title" class="label !mb-0">Page title</label>
                                <span class="text-xs tabular-nums text-muted-foreground"><span x-text="title.length"></span> / 70</span>
                            </div>
                            <input type="text" id="seo_title" name="seo_title" maxlength="70" x-model="title" :placeholder="defaultTitle"
                                   class="input {{ $errors->has('seo_title') ? 'input-error' : '' }}">
                            @error('seo_title') <p class="error-text">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <div class="mb-1.5 flex items-center justify-between">
                                <label for="seo_description" class="label !mb-0">Description</label>
                                <span class="text-xs tabular-nums text-muted-foreground"><span x-text="description.length"></span> / 160</span>
                            </div>
                            <textarea id="seo_description" name="seo_description" rows="3" maxlength="160" x-model="description" :placeholder="defaultDescription"
                                      class="textarea {{ $errors->has('seo_description') ? 'input-error' : '' }}"></textarea>
                            @error('seo_description') <p class="error-text">{{ $message }}</p> @else <p class="help">Leave empty to use sensible defaults from your name, location and bio.</p> @enderror
                        </div>

                        @if($publicUrl)
                            <div class="rounded-xl border border-border p-4">
                                <p class="text-[11px] font-medium uppercase tracking-wider text-muted-foreground">Google preview</p>
                                <p class="mt-2 truncate text-xs text-muted-foreground">{{ preg_replace('#^https?://#', '', $publicUrl) }}</p>
                                <p class="truncate text-lg text-[#1a0dab] dark:text-[#8ab4f8]" x-text="title || defaultTitle"></p>
                                <p class="line-clamp-2 text-[13px] text-muted-foreground" x-text="description || defaultDescription"></p>
                            </div>
                        @endif
                    </div>
                    <div class="card-footer flex justify-end">
                        <button type="submit" class="btn btn-primary">Save SEO</button>
                    </div>
                </form>

                <div class="border-t border-border">
                    @include('studio.portfolio.partials.image-field', [
                        'kind' => 'share',
                        'title' => 'Share image',
                        'hint' => 'Shown when your link is shared. We crop it to 1200 × 630. Without one we use your cover image or profile photo.',
                        'current' => $user->share_image_url,
                        'previewClass' => 'size-full object-cover',
                        'boxClass' => 'aspect-[1200/630] w-full max-w-sm overflow-hidden rounded-xl border border-dashed border-border bg-muted',
                        'maxMb' => 25,
                        'endpoints' => $imageEndpoints('share'),
                    ])
                </div>
            </section>
        </div>
    </div>
</div>

<script>
function contrastText(hex) {
    const m = /^#?([0-9a-f]{6})$/i.exec(hex || '');
    if (!m) return '#ffffff';
    const n = parseInt(m[1], 16);
    const lin = (c) => { c /= 255; return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4); };
    const l = 0.2126 * lin((n >> 16) & 255) + 0.7152 * lin((n >> 8) & 255) + 0.0722 * lin(n & 255);
    return 1.05 / (l + 0.05) >= (l + 0.05) / 0.0537 ? '#ffffff' : '#0c0c0e';
}

// Reorderable list (drag on desktop, arrow buttons everywhere)
function sortableList(items) {
    return {
        items,
        dragging: null,
        start(index, event) {
            this.dragging = index;
            event.dataTransfer.effectAllowed = 'move';
        },
        over(index) {
            if (this.dragging === null || this.dragging === index) return;
            const [moved] = this.items.splice(this.dragging, 1);
            this.items.splice(index, 0, moved);
            this.dragging = index;
        },
        drop() {
            this.dragging = null;
        },
        move(index, delta) {
            const target = index + delta;
            if (target < 0 || target >= this.items.length) return;
            const [moved] = this.items.splice(index, 1);
            this.items.splice(target, 0, moved);
        },
    };
}

// Direct-to-storage upload for the logo, cover and share images (same flow as the avatar uploader in Settings)
function portfolioImage(config) {
    return {
        url: config.current || null,
        uploading: false,
        progress: 0,
        statusText: 'Uploading…',
        error: null,

        async sha256Hex(file) {
            const hash = await crypto.subtle.digest('SHA-256', await file.arrayBuffer());
            return Array.from(new Uint8Array(hash)).map(b => b.toString(16).padStart(2, '0')).join('');
        },

        headers() {
            return {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            };
        },

        async pick(event) {
            const file = event.target.files && event.target.files[0];
            event.target.value = '';
            if (!file) return;
            this.error = null;

            if (!/^image\/(jpeg|png|webp)$/.test(file.type)) {
                this.error = 'Please choose a JPG, PNG or WebP image.';
                return;
            }
            if (file.size > config.maxMb * 1024 * 1024) {
                this.error = `That image is larger than ${config.maxMb} MB.`;
                return;
            }

            const previous = this.url;
            const localUrl = URL.createObjectURL(file);
            this.url = localUrl;
            this.uploading = true;
            this.progress = 5;
            this.statusText = 'Preparing…';

            try {
                const sha256 = await this.sha256Hex(file);
                const reqRes = await fetch(config.requestUrl, {
                    method: 'POST', credentials: 'same-origin', headers: this.headers(),
                    body: JSON.stringify({ filename: file.name, sha256 }),
                });
                const reqData = await reqRes.json().catch(() => ({}));
                if (!reqRes.ok || !reqData.presigned_url) throw new Error(reqData.message || 'Could not start the upload.');

                this.statusText = 'Uploading…';
                await new Promise((resolve, reject) => {
                    const xhr = new XMLHttpRequest();
                    xhr.open('PUT', reqData.presigned_url, true);
                    for (const [k, v] of Object.entries(reqData.headers || {})) {
                        try { xhr.setRequestHeader(k, v); } catch (_) {}
                    }
                    xhr.upload.onprogress = (e) => {
                        if (e.lengthComputable) this.progress = Math.max(5, Math.round((e.loaded / e.total) * 90));
                    };
                    xhr.onload = () => (xhr.status >= 200 && xhr.status < 300) ? resolve() : reject(new Error('Upload failed (' + xhr.status + ').'));
                    xhr.onerror = () => reject(new Error('Network error while uploading.'));
                    xhr.send(file);
                });

                this.statusText = 'Processing…';
                this.progress = 95;
                const confRes = await fetch(config.confirmUrl, {
                    method: 'POST', credentials: 'same-origin', headers: this.headers(),
                    body: JSON.stringify({ object_key: reqData.object_key }),
                });
                const confData = await confRes.json().catch(() => ({}));
                if (!confRes.ok) throw new Error(confData.message || 'Could not save the image.');

                this.url = confData.url || localUrl;
                if (confData.url) URL.revokeObjectURL(localUrl);
                window.toast && window.toast(config.title + ' updated', 'success');
            } catch (e) {
                this.url = previous;
                URL.revokeObjectURL(localUrl);
                this.error = e.message || 'Upload failed. Please try again.';
                window.toast && window.toast(this.error, 'error');
            } finally {
                this.uploading = false;
                this.progress = 0;
            }
        },

        async remove() {
            this.error = null;
            const res = await fetch(config.removeUrl, { method: 'DELETE', credentials: 'same-origin', headers: this.headers() });
            if (res.ok) {
                this.url = null;
                window.toast && window.toast(config.title + ' removed', 'success');
            } else {
                this.error = 'Could not remove the image.';
            }
        },
    };
}
</script>
@endsection
