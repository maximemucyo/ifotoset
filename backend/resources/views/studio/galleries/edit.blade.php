@extends('layouts.app', ['title' => 'Gallery settings · ifotoset'])

@section('content')
<div class="mx-auto max-w-5xl">
    <x-ui.page-header :title="$gallery->title"
                      eyebrow="Gallery settings"
                      description="Update details, client access and download options."
                      :back="route('studio.galleries.show', $gallery->uuid)" back-label="Back to gallery">
        <x-slot:actions>
            <a href="{{ route('studio.galleries.show', $gallery->uuid) }}" class="btn btn-outline btn-sm"><x-icon name="images" /> Manage photos</a>
        </x-slot:actions>
    </x-ui.page-header>

    <form method="POST" action="{{ route('studio.galleries.update', $gallery->uuid) }}" enctype="multipart/form-data"
          x-data="{ visibility: '{{ old('visibility', $gallery->visibility) }}', submitting: false }" @submit="submitting = true">
        @csrf
        @method('PATCH')

        @include('studio.partials.gallery-form', ['gallery' => $gallery, 'invitations' => $invitations ?? collect()])

        <div class="divider my-6"></div>

        {{-- Danger zone --}}
        <section class="grid gap-x-8 gap-y-4 lg:grid-cols-[240px_minmax(0,1fr)]">
            <div class="lg:pt-5">
                <h2 class="text-[15px] font-semibold text-foreground">Danger zone</h2>
                <p class="mt-1 text-[13px] text-muted-foreground">Trashed galleries can be restored from Trash.</p>
            </div>
            <div class="card flex flex-col gap-4 border-destructive/25 p-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <div>
                    <p class="text-sm font-medium text-foreground">Move gallery to trash</p>
                    <p class="mt-0.5 text-xs text-muted-foreground">Clients immediately lose access to this gallery.</p>
                </div>
                <button type="button"
                        onclick="if(confirm('Are you sure you want to move this gallery to trash?')) document.getElementById('delete-gallery-form').submit();"
                        class="btn btn-danger btn-sm shrink-0">
                    <x-icon name="trash-2" /> Move to trash
                </button>
            </div>
        </section>

        <div class="sticky bottom-3 z-20 mt-8 rounded-xl border border-border bg-card/90 px-3 py-2.5 shadow-lg backdrop-blur-md sm:bottom-4 sm:px-4">
            <div class="flex items-center justify-between gap-3">
                <p class="hidden text-[13px] text-muted-foreground sm:block">Last updated {{ $gallery->updated_at->diffForHumans() }}</p>
                <div class="flex flex-1 items-center justify-end gap-2">
                    <a href="{{ route('studio.galleries.show', $gallery->uuid) }}" class="btn btn-ghost">Cancel</a>
                    <button type="submit" class="btn btn-primary" :disabled="submitting">
                        <x-icon name="loader-circle" class="animate-spin" x-show="submitting" x-cloak />
                        Save changes
                    </button>
                </div>
            </div>
        </div>
    </form>

    <form id="delete-gallery-form" method="POST" action="{{ route('studio.galleries.destroy', $gallery->uuid) }}" class="hidden">
        @csrf
        @method('DELETE')
    </form>

    {{-- Standalone invitation action forms (referenced via the `form` attribute so they don't submit the settings form). --}}
    @foreach(($invitations ?? collect()) as $invitation)
        <form id="invitation-resend-{{ $invitation->id }}" method="POST" action="{{ route('studio.galleries.invitations.resend', [$gallery->uuid, $invitation->id]) }}" class="hidden">@csrf</form>
        @unless($invitation->isRevoked())
            <form id="invitation-revoke-{{ $invitation->id }}" method="POST" action="{{ route('studio.galleries.invitations.revoke', [$gallery->uuid, $invitation->id]) }}" class="hidden">@csrf</form>
        @endunless
    @endforeach
</div>
@endsection
