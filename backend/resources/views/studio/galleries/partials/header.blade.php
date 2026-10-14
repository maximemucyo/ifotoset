{{-- Gallery manager header: cover, title, status, share link, primary actions and section tabs. --}}
<header>
    <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
        <div class="flex min-w-0 items-start gap-4">
            {{-- Cover thumbnail (follows "Set as cover" live) --}}
            <div class="relative size-16 shrink-0 overflow-hidden rounded-xl bg-muted ring-1 ring-border sm:size-[72px]">
                <img x-show="coverThumb || {{ $coverThumbUrl ? 'true' : 'false' }}"
                     :src="coverThumb || @js($coverThumbUrl ?? '')"
                     @if($coverThumbUrl) src="{{ $coverThumbUrl }}" @else style="display: none;" @endif
                     alt="Cover of {{ $gallery->title }}"
                     width="72" height="72" decoding="async"
                     class="size-full object-cover" />
                <div x-show="!(coverThumb || {{ $coverThumbUrl ? 'true' : 'false' }})"
                     class="absolute inset-0 flex items-center justify-center text-subtle"
                     @if($coverThumbUrl) style="display: none;" @endif>
                    <x-icon name="images" class="size-6" />
                </div>
            </div>

            <div class="min-w-0">
                <div class="mb-1.5 flex flex-wrap items-center gap-1.5">
                    <x-ui.badge :variant="$visibilityVariant" dot>{{ $visibilityLabel }}</x-ui.badge>
                    @if(!empty($gallery->password_hash) && $gallery->visibility !== 'password')
                        <x-ui.badge variant="warning"><x-icon name="lock" class="size-3" /> PIN</x-ui.badge>
                    @endif
                    @if($isTakenDown)
                        <x-ui.badge variant="danger"><x-icon name="ban" class="size-3" /> Taken down</x-ui.badge>
                    @endif
                    @if($gallery->expires_at)
                        @if($isExpired)
                            <x-ui.badge variant="danger"><x-icon name="calendar-x" class="size-3" /> Expired {{ $gallery->expires_at->format('M j, Y') }}</x-ui.badge>
                        @else
                            <x-ui.badge variant="neutral"><x-icon name="clock" class="size-3" /> Expires {{ $gallery->expires_at->format('M j, Y') }}</x-ui.badge>
                        @endif
                    @endif
                </div>
                <h1 class="truncate text-2xl font-semibold tracking-tight text-foreground sm:text-[28px]">{{ $gallery->title }}</h1>
                <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-[13px] text-muted-foreground">
                    <span class="tabular-nums">
                        <span x-text="totalPhotos">{{ $totalPhotosCount }}</span>
                        <span x-text="totalPhotos === 1 ? 'item' : 'items'">{{ $totalPhotosCount === 1 ? 'item' : 'items' }}</span>
                    </span>
                    @if($gallery->event_date)
                        <span class="text-subtle" aria-hidden="true">·</span>
                        <span class="inline-flex items-center gap-1"><x-icon name="calendar" class="size-3.5" /> {{ $gallery->event_date->format('M j, Y') }}</span>
                    @endif
                    @if(auth()->user()->hasVideoSupport())
                        <span class="text-subtle" aria-hidden="true">·</span>
                        <span class="inline-flex items-center gap-1"><x-icon name="video" class="size-3.5" /> Video {{ auth()->user()->getVideoUsageFormatted() }}</span>
                    @endif
                </p>
            </div>
        </div>

        <div class="flex shrink-0 flex-wrap items-center gap-2">
            <a href="{{ $publicUrl }}" target="_blank" rel="noopener" class="btn btn-outline btn-sm">
                <x-icon name="eye" /> View as client
            </a>
            <button type="button" class="btn btn-primary btn-sm" @click="$dispatch('open-modal', 'upload-photos-modal')">
                <x-icon name="upload" /> Upload
            </button>
            <div x-data="dropdown" class="relative" @click.outside="close()" @keydown.escape="close()">
                <button type="button" class="btn btn-outline btn-icon btn-sm" @click="toggle()" :aria-expanded="open" aria-haspopup="menu" aria-label="More actions">
                    <x-icon name="ellipsis" />
                </button>
                <div x-show="open" x-cloak
                     x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                     x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                     class="menu absolute right-0 top-full mt-2 w-56" role="menu">
                    <a href="{{ route('studio.galleries.edit', $gallery->uuid) }}" class="menu-item" role="menuitem"><x-icon name="pencil" /> Edit details</a>
                    <a href="{{ route('studio.galleries.edit', $gallery->uuid) }}#invitations" class="menu-item" role="menuitem"><x-icon name="user-plus" /> Invite clients</a>
                    <div class="menu-separator"></div>
                    <button type="button" class="menu-item" role="menuitem" @click="$clipboard(@js($publicUrl), 'Gallery link copied'); close()"><x-icon name="link" /> Copy gallery link</button>
                    <a href="{{ $publicUrl }}" target="_blank" rel="noopener" class="menu-item" role="menuitem"><x-icon name="external-link" /> Open client view</a>
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
    </div>

    {{-- Share link + storage --}}
    <div class="mt-5 grid gap-3 md:grid-cols-[minmax(0,1fr)_auto]">
        <div class="flex min-w-0 items-center gap-2 rounded-xl border border-border bg-card p-1.5 pl-3 shadow-xs">
            <x-icon name="link" class="size-4 text-subtle" />
            <a href="{{ $publicUrl }}" target="_blank" rel="noopener"
               class="min-w-0 flex-1 truncate font-mono text-[12.5px] text-muted-foreground transition-colors hover:text-foreground"
               title="{{ $publicUrl }}">{{ preg_replace('#^https?://#', '', $publicUrl) }}</a>
            <button type="button" class="btn btn-secondary btn-xs shrink-0" @click="$clipboard(@js($publicUrl), 'Gallery link copied')">
                <x-icon name="copy" /> Copy
            </button>
        </div>
        @php
            $su = $userStorage ?? [];
            $suUnlimited = (bool) ($su['is_unlimited'] ?? false);
            $suUsed = (int) ($su['used_bytes'] ?? 0);
            $suLimit = (int) ($su['limit_bytes'] ?? 0);
            $suPct = $suUnlimited || $suLimit <= 0 ? 0 : min(100, round($suUsed / $suLimit * 100));
            $suFmt = function (int $bytes, int $precision = 1): string {
                $units = ['B', 'KB', 'MB', 'GB', 'TB'];
                $i = $bytes > 0 ? min(count($units) - 1, (int) floor(log($bytes, 1024))) : 0;
                return round($bytes / (1024 ** $i), $i === 0 ? 0 : $precision) . ' ' . $units[$i];
            };
        @endphp
        @if(!empty($su))
            <a href="{{ route('studio.billing.index') }}" class="flex items-center gap-3 rounded-xl border border-border bg-card px-3.5 py-2 shadow-xs transition-colors hover:border-border-strong md:w-64" title="Storage used across all galleries">
                <x-icon name="hard-drive" class="size-4 text-subtle" />
                <div class="min-w-0 flex-1">
                    <div class="flex items-center justify-between gap-2 text-[11px]">
                        <span class="font-medium text-foreground">Storage</span>
                        <span class="tabular-nums text-muted-foreground">
                            {{ $suFmt($suUsed, 1) }}
                            @unless($suUnlimited) / {{ $suFmt($suLimit, 0) }} @endunless
                        </span>
                    </div>
                    @unless($suUnlimited)
                        <div class="progress mt-1.5 !h-1">
                            <span class="{{ $suPct >= 90 ? '!bg-destructive' : ($suPct >= 75 ? '!bg-warning' : '') }}" style="width: {{ $suPct }}%"></span>
                        </div>
                    @endunless
                </div>
            </a>
        @endif
    </div>

    {{-- Section tabs --}}
    <nav class="tabs-line mt-6 overflow-x-auto" aria-label="Gallery sections">
        <a href="{{ route('studio.galleries.show', $gallery->uuid) }}" class="tab-line is-active shrink-0" aria-current="page">
            <x-icon name="images" class="size-4" /> Photos
            <span class="badge badge-neutral !px-1.5 !py-0 tabular-nums" x-text="totalPhotos">{{ $totalPhotosCount }}</span>
        </a>
        <a href="{{ route('studio.galleries.edit', $gallery->uuid) }}#invitations" class="tab-line shrink-0">
            <x-icon name="users" class="size-4" /> Clients &amp; invitations
            @if($invitationsCount > 0)
                <span class="badge badge-neutral !px-1.5 !py-0 tabular-nums">{{ $invitationsCount }}</span>
            @endif
        </a>
        <a href="{{ route('studio.galleries.edit', $gallery->uuid) }}" class="tab-line shrink-0">
            <x-icon name="settings" class="size-4" /> Settings
        </a>
    </nav>
</header>
