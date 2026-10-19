{{--
    Application shell shared by the Studio and Admin layouts.

    Expects:
      $context   'studio' | 'admin'
      $navGroups array of ['label' => ?string, 'items' => [['label','route','icon','badge'?,'match'?]]]
      $homeRoute route name for the logo
--}}
@php
    $user = auth()->user();
    $isAdminContext = $context === 'admin';

    $resolveActive = function (array $item): bool {
        $patterns = (array) ($item['match'] ?? []);
        if (empty($patterns)) {
            $parts = explode('.', $item['route']);
            $patterns = [$item['route'], $parts[0] . '.' . ($parts[1] ?? '') . '.*'];
        }
        return request()->routeIs(...$patterns);
    };

    // Storage meter (studio only)
    $storageUsed = (int) ($user->storage_used_bytes ?? 0);
    $storageLimit = (int) ($user->plan->storage_limit ?? 0);
    $storagePct = $storageLimit > 0 ? min(100, round($storageUsed / $storageLimit * 100, 1)) : 0;
    $fmtBytes = function (int $bytes): string {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $value = (float) $bytes;
        while ($value >= 1024 && $i < count($units) - 1) { $value /= 1024; $i++; }
        return ($i === 0 ? (int) $value : number_format($value, $value >= 100 ? 0 : 1)) . ' ' . $units[$i];
    };

    // Command palette entries
    $commandItems = [];
    foreach ($navGroups as $group) {
        foreach ($group['items'] as $item) {
            if (Route::has($item['route'])) {
                $commandItems[] = ['label' => $item['label'], 'url' => route($item['route']), 'group' => 'Navigate', 'icon' => $item['icon']];
            }
        }
    }
    if (!$isAdminContext) {
        $commandItems[] = ['label' => 'Create new gallery', 'url' => route('studio.galleries.create'), 'group' => 'Actions', 'keywords' => 'add new upload', 'icon' => 'plus'];
        if ($user?->username) {
            $commandItems[] = ['label' => 'View public portfolio', 'url' => $user->public_url, 'group' => 'Actions', 'keywords' => 'profile website', 'icon' => 'external-link', 'newTab' => true];
        }
        $commandItems[] = $user?->isFree()
            ? ['label' => 'Upgrade plan', 'url' => route('studio.billing.index'), 'group' => 'Actions', 'keywords' => 'billing pricing subscription', 'icon' => 'zap']
            : ['label' => 'Billing & plans', 'url' => route('studio.billing.index'), 'group' => 'Actions', 'keywords' => 'billing pricing subscription upgrade', 'icon' => 'credit-card'];
    }
    if ($user?->isAdmin()) {
        $commandItems[] = $isAdminContext
            ? ['label' => 'Switch to Studio', 'url' => route('studio.dashboard'), 'group' => 'Workspace', 'icon' => 'camera']
            : ['label' => 'Switch to Admin console', 'url' => route('admin.dashboard'), 'group' => 'Workspace', 'icon' => 'shield'];
    }
@endphp

<script type="application/json" id="command-items">@json($commandItems)</script>

<div class="flex min-h-dvh bg-sidebar lg:h-dvh lg:overflow-hidden" x-data="{ sidebarOpen: false }" @keydown.escape.window="sidebarOpen = false">

    {{-- Mobile backdrop --}}
    <div x-show="sidebarOpen" x-cloak
         x-transition:enter="transition-opacity duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         @click="sidebarOpen = false"
         class="fixed inset-0 z-40 bg-black/40 backdrop-blur-[2px] lg:hidden"></div>

    {{-- ================= Sidebar ================= --}}
    <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
           class="fixed inset-y-0 left-0 z-50 flex w-[260px] flex-col border-r border-sidebar-border bg-sidebar transition-transform duration-300 ease-[cubic-bezier(.16,1,.3,1)] lg:static lg:w-[248px] lg:translate-x-0 lg:border-r-0">

        {{-- Workspace header --}}
        <div class="flex h-14 shrink-0 items-center justify-between gap-2 px-4">
            <x-brand.logo :href="route($homeRoute)" size="sm" />
            @if($isAdminContext)
                <span class="badge badge-danger">Admin</span>
            @else
                <span class="badge badge-neutral capitalize">{{ $user->plan->name ?? 'Free' }}</span>
            @endif
            <button type="button" @click="sidebarOpen = false" class="btn btn-ghost btn-icon btn-sm ml-auto lg:hidden" aria-label="Close menu">
                <x-icon name="x" />
            </button>
        </div>

        {{-- Search / command trigger --}}
        <div class="px-3 pb-1">
            <button type="button" @click="$dispatch('open-command-palette')"
                    class="flex h-8 w-full items-center gap-2 rounded-lg border border-sidebar-border bg-background/60 px-2.5 text-[13px] text-muted-foreground shadow-xs transition hover:border-border-strong hover:text-foreground">
                <x-icon name="search" class="size-3.5" />
                <span class="flex-1 text-left">Search…</span>
                <span class="kbd">⌘K</span>
            </button>
        </div>

        {{-- Navigation --}}
        <nav class="flex-1 overflow-y-auto px-3 pb-4" aria-label="Main">
            @foreach($navGroups as $group)
                @if(!empty($group['label']))
                    <div class="nav-section">{{ $group['label'] }}</div>
                @else
                    <div class="h-3"></div>
                @endif
                <div class="space-y-0.5">
                    @foreach($group['items'] as $item)
                        @continue(!Route::has($item['route']))
                        @php($active = $resolveActive($item))
                        <a href="{{ route($item['route']) }}" class="nav-item" @if($active) aria-current="page" @endif>
                            <x-icon :name="$item['icon']" />
                            <span class="flex-1 truncate">{{ $item['label'] }}</span>
                            @if(!empty($item['badge']))
                                <span class="rounded-md bg-brand-strong px-1.5 text-[10px] font-semibold leading-4 text-brand-foreground">{{ $item['badge'] }}</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            @endforeach
        </nav>

        {{-- Sidebar footer --}}
        <div class="shrink-0 space-y-2 p-3">
            @if(!$isAdminContext && $storageLimit > 0)
                <a href="{{ route('studio.billing.index') }}" class="block rounded-xl border border-sidebar-border bg-background/70 p-3 shadow-xs transition hover:border-border-strong">
                    <div class="flex items-center justify-between text-xs">
                        <span class="flex items-center gap-1.5 font-medium text-foreground"><x-icon name="hard-drive" class="size-3.5 text-muted-foreground" /> Storage</span>
                        <span class="tabular-nums text-muted-foreground">{{ $storagePct }}%</span>
                    </div>
                    <div class="progress mt-2.5"><span style="width: {{ max($storagePct, 1.5) }}%" class="{{ $storagePct >= 90 ? '!bg-destructive' : ($storagePct >= 75 ? '!bg-warning' : '') }}"></span></div>
                    <p class="mt-2 text-[11px] text-muted-foreground tabular-nums">{{ $fmtBytes($storageUsed) }} of {{ $fmtBytes($storageLimit) }}</p>
                    @if($user->isFree())
                        <span class="mt-2.5 flex items-center gap-1 text-[12px] font-medium text-brand"><x-icon name="zap" class="size-3.5" /> Upgrade plan</span>
                    @endif
                </a>
            @endif

            @if($user?->isAdmin())
                <a href="{{ $isAdminContext ? route('studio.dashboard') : route('admin.dashboard') }}" class="nav-item">
                    <x-icon :name="$isAdminContext ? 'camera' : 'shield'" />
                    <span class="flex-1">{{ $isAdminContext ? 'Switch to Studio' : 'Admin console' }}</span>
                    <x-icon name="arrow-up-right" class="!size-3.5" />
                </a>
            @endif

            {{-- User menu --}}
            <div x-data="dropdown" class="relative" @click.outside="close()">
                <button type="button" @click="toggle()" class="flex w-full items-center gap-2.5 rounded-lg p-1.5 text-left transition hover:bg-sidebar-accent" :class="open && 'bg-sidebar-accent'">
                    <x-ui.avatar :name="$user->name ?? 'U'" :src="$user->avatar_url ?? null" size="sm" />
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-[13px] font-medium text-foreground">{{ $user->name ?? 'Photographer' }}</span>
                        <span class="block truncate text-[11px] text-muted-foreground">{{ $user->email ?? '' }}</span>
                    </span>
                    <x-icon name="chevrons-up-down" class="size-3.5 text-muted-foreground" />
                </button>
                <div x-show="open" x-cloak
                     x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                     x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                     class="menu absolute bottom-full left-0 right-0 mb-2">
                    <div class="px-2.5 py-2">
                        <p class="truncate text-[13px] font-medium">{{ $user->name }}</p>
                        <p class="truncate text-xs text-muted-foreground">{{ $user->email }}</p>
                    </div>
                    <div class="menu-separator"></div>
                    @unless($isAdminContext)
                        <a href="{{ route('studio.settings.index') }}" class="menu-item"><x-icon name="settings" /> Settings</a>
                        <a href="{{ route('studio.billing.index') }}" class="menu-item"><x-icon name="credit-card" /> Billing</a>
                        @if($user?->username)
                            <a href="{{ $user->public_url }}" target="_blank" rel="noopener" class="menu-item"><x-icon name="globe" /> Public portfolio <x-icon name="arrow-up-right" class="ml-auto !size-3.5" /></a>
                        @endif
                    @else
                        <a href="{{ route('admin.settings.index') }}" class="menu-item"><x-icon name="settings" /> Platform settings</a>
                    @endunless
                    <div class="menu-separator"></div>
                    <div class="flex items-center justify-between px-2.5 py-1.5">
                        <span class="text-[13px] text-foreground">Theme</span>
                        <x-ui.theme-toggle variant="segmented" class="!p-0.5" />
                    </div>
                    <div class="menu-separator"></div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="menu-item"><x-icon name="log-out" /> Sign out</button>
                    </form>
                </div>
            </div>
        </div>
    </aside>

    {{-- ================= Main panel ================= --}}
    <div class="flex min-w-0 flex-1 flex-col lg:py-2 lg:pr-2">
        <div class="flex min-h-0 flex-1 flex-col bg-background lg:overflow-hidden lg:rounded-xl lg:border lg:border-sidebar-border lg:shadow-sm">

            {{-- Top bar --}}
            <header class="sticky top-0 z-30 flex h-14 shrink-0 items-center gap-3 border-b border-border glass px-4 sm:px-6">
                <button type="button" @click="sidebarOpen = true" class="btn btn-ghost btn-icon btn-sm -ml-1.5 lg:hidden" aria-label="Open menu">
                    <x-icon name="menu" />
                </button>
                <x-brand.logo :href="route($homeRoute)" size="sm" :wordmark="false" class="lg:hidden" />

                <div class="min-w-0 flex-1">
                    @hasSection('breadcrumbs')
                        <nav class="flex items-center gap-1.5 truncate text-[13px] text-muted-foreground" aria-label="Breadcrumb">@yield('breadcrumbs')</nav>
                    @endif
                </div>

                <div class="flex items-center gap-1">
                    <button type="button" @click="$dispatch('open-command-palette')" class="btn btn-ghost btn-icon btn-sm lg:hidden" aria-label="Search">
                        <x-icon name="search" />
                    </button>
                    @hasSection('topbar-actions')
                        @yield('topbar-actions')
                    @endif
                    <x-ui.theme-toggle />
                    @if(!$isAdminContext && $user?->username)
                        <a href="{{ $user->public_url }}" target="_blank" rel="noopener" class="btn btn-ghost btn-sm hidden sm:inline-flex">
                            <x-icon name="globe" /> Portfolio
                        </a>
                    @endif
                    @if(!$isAdminContext && !request()->routeIs('studio.galleries.create'))
                        <a href="{{ route('studio.galleries.create') }}" class="btn btn-primary btn-sm ml-1">
                            <x-icon name="plus" /> <span class="hidden sm:inline">New gallery</span>
                        </a>
                    @endif
                </div>
            </header>

            {{-- Unverified email notice --}}
            @if(!$isAdminContext && $user && !$user->hasVerifiedEmail())
                <div class="flex flex-col gap-3 border-b border-warning/20 bg-warning/8 px-4 py-2.5 text-[13px] text-warning sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <p class="flex items-center gap-2 font-medium">
                        <x-icon name="mail" class="size-4" />
                        Verify your email to unlock every studio feature. Check your inbox for the link.
                    </p>
                    <form method="POST" action="{{ route('verification.send') }}" class="shrink-0">
                        @csrf
                        <button type="submit" class="btn btn-xs border-warning/30 bg-warning/10 text-warning hover:bg-warning/20">Resend link</button>
                    </form>
                </div>
            @endif

            <main id="main" class="flex-1 overflow-y-auto">
                <div class="mx-auto w-full {{ $containerClass ?? 'max-w-7xl' }} px-4 py-6 sm:px-6 sm:py-8 lg:px-8">
                    {{ $slot ?? '' }}
                    @yield('content')
                </div>
            </main>
        </div>
    </div>
</div>

{{-- ================= Command palette ================= --}}
<div x-data="commandPalette" x-show="open" x-cloak class="fixed inset-0 z-[90]" role="dialog" aria-modal="true" aria-label="Command menu"
     @keydown.escape.prevent="hide()">
    <div x-show="open" x-transition.opacity.duration.150ms class="fixed inset-0 bg-black/40 backdrop-blur-[2px]" @click="hide()"></div>
    <div class="fixed inset-x-0 top-[12vh] mx-auto w-full max-w-xl px-4">
        <div x-show="open" x-transition:enter="ease-out duration-150" x-transition:enter-start="opacity-0 scale-[0.98]" x-transition:enter-end="opacity-100 scale-100"
             class="overflow-hidden rounded-2xl border border-border bg-popover shadow-xl" @click.outside="hide()">
            <div class="flex items-center gap-3 border-b border-border px-4">
                <x-icon name="search" class="size-4 text-muted-foreground" />
                <input x-ref="input" x-model="query" type="text" placeholder="Search pages and actions…"
                       class="h-12 flex-1 bg-transparent text-sm text-foreground outline-none placeholder:text-subtle"
                       @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)" @keydown.enter.prevent="go()">
                <span class="kbd">Esc</span>
            </div>
            <ul x-ref="list" class="max-h-80 overflow-y-auto p-1.5">
                <template x-for="(item, index) in filtered" :key="item.url + item.label">
                    <li>
                        <button type="button" :data-index="index" @click="go(item)" @mousemove="active = index"
                                class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left text-sm"
                                :class="active === index ? 'bg-muted text-foreground' : 'text-muted-foreground'">
                            <span class="flex-1 truncate" x-text="item.label"></span>
                            <span class="text-[11px] text-subtle" x-text="item.group"></span>
                        </button>
                    </li>
                </template>
                <li x-show="filtered.length === 0" class="px-3 py-8 text-center text-sm text-muted-foreground">No results found.</li>
            </ul>
        </div>
    </div>
</div>
