@extends('layouts.app', ['title' => 'Create Gallery - Studio'])

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <div class="flex items-center justify-between pb-4 border-b border-border">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-foreground">Create New Gallery</h1>
            <p class="text-xs text-muted-foreground mt-1">Set up a new collection to deliver photos to your client.</p>
        </div>
        <a href="{{ route('studio.galleries.index') }}" class="text-xs font-semibold text-muted-foreground hover:text-foreground">
            &larr; Back to Galleries
        </a>
    </div>

    <form method="POST" action="{{ route('studio.galleries.store') }}" class="space-y-6" x-data="{ visibility: '{{ old('visibility', 'private') }}' }">
        @csrf

        <x-ui.input label="Gallery Title"
                    name="title"
                    required
                    autofocus
                    placeholder="e.g. Wedding of Diane & Kevin"
                    :value="old('title')"
                    :error="$errors->first('title')" />

        <div class="grid sm:grid-cols-2 gap-4">
            <x-ui.input label="Client Name (Optional)"
                        name="client_name"
                        placeholder="Diane Mukamisha"
                        :value="old('client_name')"
                        :error="$errors->first('client_name')" />

            <x-ui.input label="Event Date"
                        type="date"
                        name="event_date"
                        :value="old('event_date', date('Y-m-d'))"
                        :error="$errors->first('event_date')" />
        </div>

        <div class="space-y-2">
            <label class="block text-sm font-medium text-foreground">Privacy & Visibility</label>
            <div class="grid sm:grid-cols-3 gap-3">
                <label class="cursor-pointer border rounded-xl p-3 flex flex-col gap-1 transition-all"
                       :class="visibility === 'private' ? 'border-primary bg-primary/5 shadow-sm' : 'border-border bg-card'">
                    <div class="flex items-center justify-between">
                        <span class="font-semibold text-xs text-foreground">Unlisted / Private</span>
                        <input type="radio" name="visibility" value="private" x-model="visibility" class="text-primary focus:ring-primary">
                    </div>
                    <span class="text-[11px] text-muted-foreground">Direct link only</span>
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
                       :class="visibility === 'public' ? 'border-primary bg-primary/5 shadow-sm' : 'border-border bg-card'">
                    <div class="flex items-center justify-between">
                        <span class="font-semibold text-xs text-foreground">Public</span>
                        <input type="radio" name="visibility" value="public" x-model="visibility" class="text-primary focus:ring-primary">
                    </div>
                    <span class="text-[11px] text-muted-foreground">Visible on public portfolio</span>
                </label>
            </div>
        </div>

        <div x-show="visibility === 'password'" class="pt-2" style="{{ old('visibility', 'private') === 'password' ? '' : 'display: none;' }}">
            <x-ui.input label="Set PIN / Password"
                        type="password"
                        name="password"
                        placeholder="Choose a 4-8 character PIN"
                        :error="$errors->first('password')" />
        </div>

        <div class="border-t border-border pt-4 space-y-3">
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="allow_photo_downloads" value="1" checked class="rounded border-border text-primary focus:ring-primary h-4 w-4 bg-input">
                <span class="text-xs font-medium text-foreground">Allow clients to download individual photos</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="allow_gallery_downloads" value="1" checked class="rounded border-border text-primary focus:ring-primary h-4 w-4 bg-input">
                <span class="text-xs font-medium text-foreground">Allow clients to download the entire collection as a ZIP file</span>
            </label>
        </div>

        <div class="pt-4 flex justify-end gap-3">
            <a href="{{ route('studio.galleries.index') }}" class="px-4 py-2 text-sm font-semibold text-muted-foreground hover:text-foreground">
                Cancel
            </a>
            <x-ui.button type="submit" variant="primary">
                Create Gallery &rarr;
            </x-ui.button>
        </div>
    </form>
</div>
@endsection
