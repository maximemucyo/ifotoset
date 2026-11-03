{{--
    Gallery workspace: the frame around a gallery's Photos, Design, Clients and Settings tabs. A top bar with the
    title, status and Share, and a rail with the cover, the tabs and the Settings sections with what each is set to.
    Pages using it extend layouts.app with 'workspace' => true and wrap it in an Alpine scope with `coverThumb` and
    `totalPhotos`. The slot can read `section`, the Settings section picked from the URL hash.
--}}
@props(['gallery', 'active' => 'photos'])
@php
    $publicUrl = $gallery->public_url;
    $previewUrl = route('studio.galleries.preview', $gallery->uuid);
    $settingsUrl = route('studio.galleries.edit', $gallery->uuid);
    $designUrl = route('studio.galleries.design', $gallery->uuid);
    $isDraft = $gallery->isDraft();
    $isTakenDown = $gallery->moderation_status === 'taken_down';
    $isExpired = $gallery->expires_at?->isPast() ?? false;
    $status = match (true) {
        $isDraft => ['label' => 'Draft', 'class' => 'bg-muted text-foreground', 'note' => "Clients can't open the link yet."],
        $isExpired => ['label' => 'Expired', 'class' => 'bg-destructive/10 text-destructive', 'note' => 'The link stopped working when the gallery expired.'],
        default => ['label' => 'Shared', 'class' => 'bg-success/12 text-success', 'note' => 'Clients can open the link.'],
    };
    $coverUrl = $gallery->cover_photo_id ? $gallery->coverPhoto?->getThumbnailUrl('md') : null;
    $invitationsCount = $gallery->invitations()->count();
    $tabs = [
        'photos' => ['label' => 'Photos', 'icon' => 'images', 'url' => route('studio.galleries.show', $gallery->uuid)],
        'design' => ['label' => 'Design', 'icon' => 'palette', 'url' => $designUrl],
        'clients' => ['label' => 'Clients', 'icon' => 'users', 'url' => route('studio.galleries.clients', $gallery->uuid), 'count' => $invitationsCount ?: null],
        'settings' => ['label' => 'Settings', 'icon' => 'settings', 'url' => $settingsUrl],
    ];
    // Settings sections, each shown in the rail with what it is set to
    $sections = ['details' => 'Details', 'privacy' => 'Privacy', 'expiry' => 'Expiry', 'downloads' => 'Downloads'];
    $summary = $gallery->settingsSummary();
@endphp
<div class="flex min-h-0 flex-1 flex-col"
     x-data="{
        railOpen: (() => { try { return localStorage.getItem('gallery-rail') !== 'closed' } catch (e) { return true } })(),
        title: @js($gallery->title),
        summary: @js($summary),
        section: 'details',
        init() {
            this.readSection();
            window.addEventListener('hashchange', () => this.readSection());
        },
        // Older links point at #invitations, #photo-order and #cover
        readSection() {
            const hash = location.hash.slice(1);
            if (hash === 'cover' && @js($active === 'settings')) return location.replace(@js($designUrl));
            const key = { invitations: 'privacy', 'photo-order': 'details' }[hash] || hash;
            this.section = @js(array_keys($sections)).includes(key) ? key : 'details';
            // Wide screens show one section at a time, so start it at the top
            if (window.matchMedia('(min-width: 1024px)').matches) document.getElementById('gallery-workspace-content')?.scrollTo(0, 0);
        },
        toggleRail() {
            this.railOpen = !this.railOpen;
            try { localStorage.setItem('gallery-rail', this.railOpen ? 'open' : 'closed') } catch (e) {}
        },
     }"
     :style="{ '--gw-rail': railOpen ? '272px' : '64px' }"
     @gallery-settings-saved.window="summary = $event.detail.summary; title = $event.detail.title">

    {{-- ============ Top bar ============ --}}
    <header class="sticky top-0 z-30 flex h-16 shrink-0 items-center gap-2 border-b border-border bg-background px-3 sm:gap-3 sm:px-4">
        <button type="button" @click="sidebarOpen = true" class="btn btn-ghost btn-icon btn-sm max-sm:hidden" aria-label="Open menu">
            <x-icon name="menu" />
        </button>
        <a href="{{ route('studio.galleries.index') }}" class="btn btn-ghost btn-icon btn-sm -ml-1" aria-label="Back to galleries" title="Back to galleries">
            <x-icon name="chevron-left" />
        </a>

        <div class="min-w-0 max-sm:flex-1">
            <h1 class="truncate text-[15px] font-semibold leading-tight text-foreground sm:text-base" x-text="title">{{ $gallery->title }}</h1>
            @if($gallery->event_date)
                <p class="truncate text-[12px] text-muted-foreground">{{ $gallery->event_date->format('F j, Y') }}</p>
            @else
                <a href="{{ $settingsUrl }}#details" class="text-[12px] text-muted-foreground hover:text-foreground">Add event date</a>
            @endif
        </div>

        {{-- Status: also where a gallery is shared or moved back to draft --}}
        <div x-data="dropdown" class="relative shrink-0" @click.outside="close()" @keydown.escape="close()">
            <button type="button" @click="toggle()" :aria-expanded="open" aria-haspopup="menu"
                    class="inline-flex h-6 items-center gap-1 rounded-full px-2 text-[12px] sm:h-7 sm:px-2.5 font-semibold transition-opacity hover:opacity-80 {{ $status['class'] }}">
                {{ $status['label'] }} <x-icon name="chevron-down" class="size-3.5" />
            </button>
            <div x-show="open" x-cloak x-transition.opacity.duration.100ms class="menu absolute left-0 top-full mt-2 w-64" role="menu">
                <p class="px-2.5 py-2 text-[12px] text-muted-foreground">{{ $status['note'] }}</p>
                <div class="menu-separator"></div>
                @if($isDraft)
                    <button type="button" class="menu-item" role="menuitem" @click="close(); $dispatch('open-share-panel')"><x-icon name="share-2" /> Share gallery</button>
                @else
                    <button type="button" class="menu-item" role="menuitem" @click="$clipboard(@js($publicUrl), 'Gallery link copied'); close()"><x-icon name="link" /> Copy link</button>
                    @if($isExpired)
                        <a href="{{ $settingsUrl }}#expiry" class="menu-item" role="menuitem"><x-icon name="calendar" /> Change expiry date</a>
                    @endif
                    <form method="POST" action="{{ route('studio.galleries.unpublish', $gallery->uuid) }}"
                          onsubmit="return confirm('Move back to draft? The link stops working until you share the gallery again.')">
                        @csrf
                        <button type="submit" class="menu-item w-full" role="menuitem"><x-icon name="eye-off" /> Move back to draft</button>
                    </form>
                @endif
            </div>
        </div>
        @if($isTakenDown)
            <x-ui.badge variant="danger" class="hidden shrink-0 sm:inline-flex"><x-icon name="ban" class="size-3" /> Taken down</x-ui.badge>
        @endif

        <div class="ml-auto flex shrink-0 items-center gap-1 sm:gap-2">
            <a href="{{ $previewUrl }}" target="_blank" rel="noopener" class="btn btn-ghost btn-sm max-sm:hidden" title="See the gallery as your client will">Preview</a>
            <button type="button" class="btn btn-primary btn-sm" @click="$dispatch('open-share-panel')">
                <x-icon name="share-2" /> Share
            </button>
            <div x-data="dropdown" class="relative" @click.outside="close()" @keydown.escape="close()">
                <button type="button" class="btn btn-ghost btn-icon btn-sm" @click="toggle()" :aria-expanded="open" aria-haspopup="menu" aria-label="More actions">
                    <x-icon name="ellipsis" />
                </button>
                <div x-show="open" x-cloak x-transition.opacity.duration.100ms class="menu absolute right-0 top-full mt-2 w-56" role="menu">
                    <a href="{{ $previewUrl }}" target="_blank" rel="noopener" class="menu-item sm:hidden" role="menuitem"><x-icon name="eye" /> Preview</a>
                    <button type="button" class="menu-item" role="menuitem" @click="$clipboard(@js($publicUrl), 'Gallery link copied'); close()"><x-icon name="link" /> Copy gallery link</button>
                    @unless($isDraft)
                        <a href="{{ $publicUrl }}" target="_blank" rel="noopener" class="menu-item" role="menuitem"><x-icon name="external-link" /> Open client link</a>
                    @endunless
                    <a href="{{ route('studio.galleries.clients', $gallery->uuid) }}" class="menu-item" role="menuitem"><x-icon name="user-plus" /> Invite by email</a>
                    <div class="menu-separator"></div>
                    <form method="POST" action="{{ route('studio.galleries.destroy', $gallery->uuid) }}"
                          onsubmit="return confirm('Move this gallery to trash? Clients will immediately lose access.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="menu-item menu-item-danger w-full" role="menuitem"><x-icon name="trash-2" /> Move to trash</button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    <div class="flex min-h-0 flex-1 flex-col lg:flex-row">

        {{-- ============ Rail ============ --}}
        <aside class="shrink-0 border-b border-border bg-background lg:flex lg:w-[var(--gw-rail)] lg:flex-col lg:overflow-y-auto lg:border-b-0 lg:border-r"
               aria-label="Gallery">

            {{-- Cover: opens the Design tab --}}
            <a href="{{ $designUrl }}" x-show="railOpen"
               class="group/cover relative hidden aspect-[3/2] shrink-0 overflow-hidden bg-muted lg:block"
               title="Change cover">
                <img x-show="coverThumb || {{ $coverUrl ? 'true' : 'false' }}"
                     :src="coverThumb || @js($coverUrl ?? '')"
                     @if($coverUrl) src="{{ $coverUrl }}" @else style="display: none;" @endif
                     alt="Cover of {{ $gallery->title }}" decoding="async"
                     class="size-full object-cover" style="object-position: {{ $gallery->coverObjectPosition() }}" />
                <span class="absolute inset-0 flex items-center justify-center gap-2 bg-black/45 text-[13px] font-medium text-white opacity-0 transition-opacity group-hover/cover:opacity-100 group-focus-visible/cover:opacity-100">
                    <x-icon name="pencil" class="size-4" /> Change cover
                </span>
                <span x-show="!(coverThumb || {{ $coverUrl ? 'true' : 'false' }})" @if($coverUrl) style="display: none;" @endif
                      class="absolute inset-0 flex items-center justify-center px-6 text-center text-[12px] text-muted-foreground">
                    The first photo you add becomes the cover
                </span>
            </a>

            {{-- Tabs: a row under the top bar on phones, a column of three in the rail --}}
            <nav class="group/tabs flex overflow-x-auto px-2 lg:shrink-0 lg:border-b lg:border-border lg:px-0"
                 :class="railOpen ? 'lg:flex-row' : 'lg:flex-col lg:py-2'"
                 :data-collapsed="!railOpen"
                 aria-label="Gallery sections">
                @foreach($tabs as $key => $tab)
                    <a href="{{ $tab['url'] }}" title="{{ $tab['label'] }}"
                       @if($active === $key) aria-current="page" @endif
                       @class([
                           'relative flex shrink-0 items-center gap-1.5 px-3 py-3 text-[13px] transition-colors lg:flex-1 lg:flex-col lg:gap-1 lg:px-1 lg:text-[12px]',
                           'font-medium text-foreground shadow-[inset_0_-2px_0_var(--foreground)] lg:group-data-[collapsed=true]/tabs:shadow-[inset_2px_0_0_var(--foreground)]' => $active === $key,
                           'text-muted-foreground hover:text-foreground' => $active !== $key,
                       ])>
                        <x-icon :name="$tab['icon']" class="size-[18px]" />
                        <span :class="railOpen ? '' : 'lg:sr-only'">
                            {{ $tab['label'] }}
                            @if($key === 'photos')
                                <span class="tabular-nums text-muted-foreground lg:hidden" x-text="totalPhotos">{{ $gallery->photo_count }}</span>
                            @elseif(!empty($tab['count']))
                                <span class="tabular-nums text-muted-foreground lg:hidden">{{ $tab['count'] }}</span>
                            @endif
                        </span>
                    </a>
                @endforeach
            </nav>

            {{-- Settings sections and what each is set to --}}
            <nav x-show="railOpen" class="hidden px-2 py-3 lg:block" aria-label="Settings">
                @foreach($sections as $key => $label)
                    <a href="{{ $settingsUrl }}#{{ $key }}"
                       @if($active === 'settings') :aria-current="section === '{{ $key }}' && 'page'" :class="section === '{{ $key }}' ? 'bg-muted' : 'hover:bg-muted/60'" @endif
                       @class(['flex items-baseline justify-between gap-3 rounded-lg px-3 py-2 transition-colors', 'hover:bg-muted/60' => $active !== 'settings'])>
                        <span class="shrink-0 text-[13px] text-muted-foreground">{{ $label }}</span>
                        <span class="truncate text-[13px] font-medium text-foreground" x-text="summary.{{ $key }}">{{ $summary[$key] }}</span>
                    </a>
                @endforeach
            </nav>

            <div class="mt-auto hidden shrink-0 p-3 lg:block">
                <div x-show="railOpen" class="mb-3">
                    @if($isDraft)
                        <p class="px-1 text-[12px] leading-relaxed text-muted-foreground">Clients can't open the link until you share the gallery.</p>
                    @else
                        <button type="button" class="flex w-full min-w-0 items-center gap-2 rounded-lg border border-border px-3 py-2 text-left transition-colors hover:border-border-strong"
                                @click="$clipboard(@js($publicUrl), 'Gallery link copied')" title="Copy {{ $publicUrl }}">
                            <span class="min-w-0 flex-1 truncate text-[12px] text-muted-foreground">{{ preg_replace('#^https?://#', '', $publicUrl) }}</span>
                            <x-icon name="copy" class="size-3.5 shrink-0 text-muted-foreground" />
                        </button>
                    @endif
                </div>
                <button type="button" class="btn btn-ghost btn-icon btn-sm" @click="toggleRail()"
                        :aria-label="railOpen ? 'Collapse panel' : 'Expand panel'" :title="railOpen ? 'Collapse panel' : 'Expand panel'">
                    <x-icon name="arrow-left-to-line" x-show="railOpen" />
                    <x-icon name="arrow-right-to-line" x-show="!railOpen" x-cloak />
                </button>
            </div>
        </aside>

        {{-- ============ Tab content ============ --}}
        <div class="min-w-0 flex-1 lg:overflow-y-auto" id="gallery-workspace-content">
            <div class="px-4 py-6 sm:px-6 lg:px-8">
                {{ $slot }}
            </div>
        </div>
    </div>

    @include('studio.galleries.partials.share-panel', ['gallery' => $gallery, 'publicUrl' => $publicUrl])
</div>
