@extends('layouts.admin', ['title' => 'Moderation · Admin'])

@section('content')
<div x-data="{ takedown: { url: '', title: '' } }">
    <x-ui.page-header title="Moderation" description="Review private and password-protected collections before they go public. Approve to publish, reject to keep private, or take down content that breaks the rules.">
        <x-slot:actions>
            <span class="inline-flex h-8 items-center gap-2 rounded-lg border border-border bg-card px-3 text-[13px] text-muted-foreground shadow-xs">
                <span class="relative flex size-2">
                    @if($galleries->total() > 0)
                        <span class="absolute inline-flex size-full animate-ping rounded-full bg-warning opacity-60"></span>
                    @endif
                    <span class="relative inline-flex size-2 rounded-full {{ $galleries->total() > 0 ? 'bg-warning' : 'bg-success' }}"></span>
                </span>
                <span><span class="font-semibold tabular-nums text-foreground">{{ number_format($galleries->total()) }}</span> awaiting review</span>
            </span>
        </x-slot:actions>
    </x-ui.page-header>

    @if($galleries->isEmpty())
        <x-ui.empty-state icon="shield-check" title="Queue is clear" description="There are no collections waiting for review. New private or password-protected galleries will show up here." />
    @else
        <div class="card overflow-hidden">
            <div class="flex items-center justify-between gap-3 border-b border-border px-4 py-3 sm:px-5">
                <h2 class="text-[13px] font-medium text-foreground">Review queue</h2>
                <p class="text-xs text-muted-foreground">Newest first · <span class="tabular-nums">{{ $galleries->firstItem() }}–{{ $galleries->lastItem() }}</span> of <span class="tabular-nums">{{ number_format($galleries->total()) }}</span></p>
            </div>

            <ul class="divide-y divide-border">
                @foreach($galleries as $gallery)
                    @php
                        $cover = $gallery->getCoverUrl('sm');
                        $hasPublic = $gallery->user && $gallery->user->username;
                    @endphp
                    <li class="group flex flex-col gap-4 px-4 py-4 transition-colors hover:bg-muted/30 sm:px-5 lg:flex-row lg:items-center">
                        <div class="flex min-w-0 flex-1 items-start gap-4">
                            {{-- Thumbnail --}}
                            <a href="{{ route('admin.galleries.preview', $gallery->uuid) }}"
                               class="relative aspect-[4/3] w-24 shrink-0 overflow-hidden rounded-lg bg-muted ring-1 ring-border sm:w-28">
                                @if($cover)
                                    <img src="{{ $cover }}" alt="{{ $gallery->title }}" loading="lazy" decoding="async"
                                         class="size-full object-cover transition-transform duration-300 group-hover:scale-[1.03]">
                                @else
                                    <div class="flex size-full flex-col items-center justify-center gap-1 text-subtle">
                                        <x-icon name="images" class="size-5" />
                                        <span class="text-[10px]">No cover</span>
                                    </div>
                                @endif
                                <span class="absolute bottom-1 right-1 rounded bg-black/60 px-1.5 py-0.5 text-[10px] font-medium tabular-nums text-white backdrop-blur-sm">{{ number_format($gallery->photo_count) }}</span>
                            </a>

                            {{-- Details --}}
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <h3 class="truncate text-sm font-semibold text-foreground">{{ $gallery->title }}</h3>
                                    @if($gallery->visibility === 'password')
                                        <x-ui.badge variant="warning"><x-icon name="lock" /> Password</x-ui.badge>
                                    @else
                                        <x-ui.badge variant="neutral"><x-icon name="eye-off" /> {{ ucfirst($gallery->visibility) }}</x-ui.badge>
                                    @endif
                                    @if($gallery->isTakenDown())
                                        <x-ui.badge variant="danger"><x-icon name="ban" /> Taken down</x-ui.badge>
                                    @endif
                                </div>
                                <div class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
                                    <span class="inline-flex min-w-0 items-center gap-1.5">
                                        <x-ui.avatar :name="$gallery->user->name ?? 'Photographer'" size="xs" />
                                        <span class="truncate font-medium text-foreground">{{ $gallery->user->name ?? 'Photographer' }}</span>
                                        @if($hasPublic)<span class="truncate text-subtle">{{ '@' . $gallery->user->username }}</span>@endif
                                    </span>
                                    <span class="inline-flex items-center gap-1"><x-icon name="image" class="size-3" /> {{ number_format($gallery->photo_count) }} {{ \Illuminate\Support\Str::plural('photo', $gallery->photo_count) }}</span>
                                    <span class="inline-flex items-center gap-1" title="{{ $gallery->created_at->format('M j, Y g:i A') }}"><x-icon name="clock" class="size-3" /> {{ $gallery->created_at->diffForHumans() }}</span>
                                </div>
                            </div>
                        </div>

                        {{-- Actions --}}
                        <div class="flex flex-wrap items-center gap-2 lg:shrink-0 lg:flex-nowrap lg:justify-end">
                            <div class="flex items-center gap-1 max-lg:w-full">
                                <a href="{{ route('admin.galleries.preview', $gallery->uuid) }}" class="btn btn-ghost btn-sm">
                                    <x-icon name="eye" /> Preview
                                </a>
                                @if($hasPublic)
                                    <a href="{{ $gallery->public_url }}" target="_blank" rel="noopener" class="btn btn-ghost btn-icon btn-sm" title="Open public page" aria-label="Open public page">
                                        <x-icon name="external-link" />
                                    </a>
                                @endif
                                <span class="mx-1 hidden h-5 w-px bg-border lg:block"></span>
                            </div>

                            <div class="flex flex-1 items-center gap-2 lg:flex-none">
                                <form method="POST" action="{{ route('admin.moderation.action', $gallery->uuid) }}" onsubmit="return confirm('Reject this gallery?');" class="max-lg:flex-1">
                                    @csrf
                                    <input type="hidden" name="decision" value="reject">
                                    <button type="submit" class="btn btn-outline btn-sm max-lg:w-full"><x-icon name="x" /> Reject</button>
                                </form>

                                @unless($gallery->isTakenDown())
                                    <button type="button" class="btn btn-danger-ghost btn-sm max-lg:flex-1"
                                            @click="takedown = { url: @js(route('admin.galleries.takedown', $gallery->uuid)), title: @js($gallery->title) }; $dispatch('open-modal', 'moderation-takedown')">
                                        <x-icon name="ban" /> Take down
                                    </button>
                                @endunless

                                <form method="POST" action="{{ route('admin.moderation.action', $gallery->uuid) }}" class="max-lg:flex-1">
                                    @csrf
                                    <input type="hidden" name="decision" value="approve">
                                    <button type="submit" class="btn btn-primary btn-sm max-lg:w-full"><x-icon name="check" /> Approve</button>
                                </form>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>

            @if($galleries->hasPages())
                <div class="border-t border-border px-4 py-3 sm:px-5">
                    {{ $galleries->links() }}
                </div>
            @endif
        </div>
    @endif

    {{-- Take-down modal (shared) --}}
    <x-ui.modal name="moderation-takedown" title="Take down gallery" description="The gallery is hidden from clients and the public immediately. The photographer keeps their files and you can restore it later from Galleries.">
        <form method="POST" :action="takedown.url" class="space-y-4" x-data="{ busy: false }" @submit="busy = true">
            @csrf
            <div class="flex items-center gap-2 rounded-lg border border-border bg-muted/50 px-3 py-2 text-[13px]">
                <x-icon name="images" class="size-4 text-muted-foreground" />
                <span class="truncate font-medium text-foreground" x-text="takedown.title"></span>
            </div>
            <div>
                <label class="label" for="takedown-reason">Reason</label>
                <textarea id="takedown-reason" name="reason" required minlength="3" maxlength="500" rows="3" class="textarea"
                          placeholder="e.g. Copyright complaint received, content violates guidelines…"></textarea>
                <p class="help">Recorded in the audit log.</p>
            </div>
            <div class="flex justify-end gap-2 pt-1">
                <button type="button" class="btn btn-ghost btn-sm" @click="$dispatch('close-modal', 'moderation-takedown')">Cancel</button>
                <button type="submit" class="btn btn-danger btn-sm" :disabled="busy">
                    <x-icon name="loader-circle" class="animate-spin" x-show="busy" x-cloak />
                    <x-icon name="ban" x-show="!busy" />
                    Take down
                </button>
            </div>
        </form>
    </x-ui.modal>
</div>
@endsection
