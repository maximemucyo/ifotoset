@extends('layouts.app', ['title' => 'Gallery Settings - Studio'])

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <div class="flex items-center justify-between pb-4 border-b border-border">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-foreground">Gallery Settings</h1>
            <p class="text-xs text-muted-foreground mt-1">Update title, event date, and client privacy settings.</p>
        </div>
        <a href="{{ route('studio.galleries.show', $gallery->uuid) }}" class="text-xs font-semibold text-muted-foreground hover:text-foreground">
            &larr; Back to Gallery
        </a>
    </div>

    <form method="POST" action="{{ route('studio.galleries.update', $gallery->uuid) }}" class="space-y-6" x-data="{ visibility: '{{ $gallery->visibility }}' }">
        @csrf
        @method('PATCH')

        <x-ui.input label="Gallery Title"
                    name="title"
                    required
                    :value="old('title', $gallery->title)"
                    :error="$errors->first('title')" />

        <div class="grid sm:grid-cols-2 gap-4">
            <x-ui.input label="Client Name (Optional)"
                        name="client_name"
                        :value="old('client_name', $gallery->client_name)"
                        :error="$errors->first('client_name')" />

            <x-ui.input label="Event Date"
                        type="date"
                        name="event_date"
                        :value="old('event_date', $gallery->event_date ? \Carbon\Carbon::parse($gallery->event_date)->format('Y-m-d') : '')"
                        :error="$errors->first('event_date')" />
        </div>

        <div class="space-y-2">
            <label class="block text-sm font-medium text-foreground">Privacy & Visibility</label>
            <div class="grid sm:grid-cols-3 gap-3">
                <label class="cursor-pointer border rounded-xl p-3 flex flex-col gap-1 transition-all"
                       :class="visibility === 'public' ? 'border-primary bg-primary/5 shadow-sm' : 'border-border bg-card'">
                    <div class="flex items-center justify-between">
                        <span class="font-semibold text-xs text-foreground">Public</span>
                        <input type="radio" name="visibility" value="public" x-model="visibility" class="text-primary focus:ring-primary">
                    </div>
                    <span class="text-[11px] text-muted-foreground">Visible on public portfolio</span>
                </label>

                <label class="cursor-pointer border rounded-xl p-3 flex flex-col gap-1 transition-all"
                       :class="visibility === 'password' ? 'border-primary bg-primary/5 shadow-sm' : 'border-border bg-card'">
                    <div class="flex items-center justify-between">
                        <span class="font-semibold text-xs text-foreground">PIN Protected</span>
                        <input type="radio" name="visibility" value="password" x-model="visibility" class="text-primary focus:ring-primary">
                    </div>
                    <span class="text-[11px] text-muted-foreground">Requires PIN / Password</span>
                </label>

                <label class="cursor-pointer border rounded-xl p-3 flex flex-col gap-1 transition-all"
                       :class="visibility === 'private' ? 'border-primary bg-primary/5 shadow-sm' : 'border-border bg-card'">
                    <div class="flex items-center justify-between">
                        <span class="font-semibold text-xs text-foreground">Unlisted / Private</span>
                        <input type="radio" name="visibility" value="private" x-model="visibility" class="text-primary focus:ring-primary">
                    </div>
                    <span class="text-[11px] text-muted-foreground">Direct link only</span>
                </label>
            </div>
        </div>

        <div x-show="visibility === 'password'" class="pt-2" style="{{ $gallery->visibility === 'password' ? '' : 'display: none;' }}">
            <x-ui.input label="Set New PIN / Password"
                        type="password"
                        name="password"
                        placeholder="Leave blank to keep existing password"
                        :error="$errors->first('password')" />
        </div>

        <div class="border-t border-border pt-4 space-y-3">
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="allow_photo_downloads" value="1" {{ $gallery->allow_photo_downloads ? 'checked' : '' }} class="rounded border-border text-primary focus:ring-primary h-4 w-4 bg-input">
                <span class="text-xs font-medium text-foreground">Allow photo downloads</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="allow_gallery_downloads" value="1" {{ $gallery->allow_gallery_downloads ? 'checked' : '' }} class="rounded border-border text-primary focus:ring-primary h-4 w-4 bg-input">
                <span class="text-xs font-medium text-foreground">Allow full gallery ZIP download</span>
            </label>
        </div>

        <div class="pt-4 flex items-center justify-between border-t border-border">
            <button type="button"
                    onclick="if(confirm('Are you sure you want to move this gallery to trash?')) document.getElementById('delete-gallery-form').submit();"
                    class="text-xs font-semibold text-destructive hover:underline cursor-pointer">
                Move Gallery to Trash
            </button>

            <div class="flex gap-3">
                <a href="{{ route('studio.galleries.show', $gallery->uuid) }}" class="px-4 py-2 text-sm font-semibold text-muted-foreground hover:text-foreground">
                    Cancel
                </a>
                <x-ui.button type="submit" variant="primary">
                    Save Changes
                </x-ui.button>
            </div>
        </div>
    </form>

    <form id="delete-gallery-form" method="POST" action="{{ route('studio.galleries.destroy', $gallery->uuid) }}" class="hidden">
        @csrf
        @method('DELETE')
    </form>
</div>
@endsection
