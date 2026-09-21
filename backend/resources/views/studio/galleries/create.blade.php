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

    <form method="POST" action="{{ route('studio.galleries.store') }}" enctype="multipart/form-data" class="space-y-6" x-data="{ visibility: '{{ old('visibility', 'private') }}' }">
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
                        <span class="font-semibold text-xs text-foreground">Private</span>
                        <input type="radio" name="visibility" value="private" x-model="visibility" class="text-primary focus:ring-primary">
                    </div>
                    <span class="text-[11px] text-muted-foreground">Restricted to invited client emails</span>
                </label>

                <label class="cursor-pointer border rounded-xl p-3 flex flex-col gap-1 transition-all"
                       :class="visibility === 'password' ? 'border-primary bg-primary/5 shadow-sm' : 'border-border bg-card'">
                    <div class="flex items-center justify-between">
                        <span class="font-semibold text-xs text-foreground">PIN Protected</span>
                        <input type="radio" name="visibility" value="password" x-model="visibility" class="text-primary focus:ring-primary">
                    </div>
                    <span class="text-[11px] text-muted-foreground">Requires gallery PIN to access</span>
                </label>

                <label class="cursor-pointer border rounded-xl p-3 flex flex-col gap-1 transition-all"
                       :class="visibility === 'public' ? 'border-primary bg-primary/5 shadow-sm' : 'border-border bg-card'">
                    <div class="flex items-center justify-between">
                        <span class="font-semibold text-xs text-foreground">Public</span>
                        <input type="radio" name="visibility" value="public" x-model="visibility" class="text-primary focus:ring-primary">
                    </div>
                    <span class="text-[11px] text-muted-foreground">Accessible via direct link</span>
                </label>
            </div>
        </div>

        <!-- Public: Show on Profile Checkbox -->
        <div x-show="visibility === 'public'" class="p-4 rounded-xl bg-card border border-border space-y-1">
            <label class="flex items-start gap-3 cursor-pointer">
                <input type="checkbox" name="show_on_profile" value="1" {{ old('show_on_profile') ? 'checked' : '' }} class="mt-0.5 rounded border-border text-primary focus:ring-primary h-4 w-4 bg-input">
                <div>
                    <span class="text-xs font-semibold text-foreground block">Show on my public portfolio / profile</span>
                    <span class="text-[11px] text-muted-foreground block">When unchecked, this gallery is accessible via direct link only and will not appear in your public profile showcase.</span>
                </div>
            </label>
        </div>

        <!-- PIN Protection Settings -->
        <div x-show="visibility === 'password'" class="pt-2" style="{{ old('visibility', 'private') === 'password' ? '' : 'display: none;' }}">
            <x-ui.input label="Set Gallery PIN"
                        type="password"
                        name="password"
                        placeholder="Choose a 4-8 character PIN"
                        :error="$errors->first('password')" />
        </div>

        <!-- Private: Email Invitations -->
        <div x-show="visibility === 'private'" class="space-y-4 pt-2" style="{{ old('visibility', 'private') === 'private' ? '' : 'display: none;' }}">
            <div class="p-4 rounded-xl bg-card border border-border space-y-3">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-xs font-bold text-foreground uppercase tracking-wider">Invite Recipients</h3>
                        <p class="text-[11px] text-muted-foreground">Recipients will receive a secure, personal invitation link via email.</p>
                    </div>
                    <a href="{{ route('studio.galleries.invitations.template') }}" class="text-[11px] font-semibold text-primary hover:underline flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Download CSV Template
                    </a>
                </div>

                <div>
                    <label class="block text-xs font-medium text-foreground mb-1">Enter Emails (comma, semicolon, or newline separated)</label>
                    <textarea name="invite_emails" rows="3" placeholder="client1@example.com, client2@example.com" class="w-full rounded-xl border border-border bg-input px-3 py-2 text-xs text-foreground placeholder:text-muted-foreground focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary">{{ old('invite_emails') }}</textarea>
                </div>

                <div>
                    <label class="block text-xs font-medium text-foreground mb-1">Or Upload CSV / TSV File</label>
                    <input type="file" name="invite_file" accept=".csv,.tsv,.txt" class="block w-full text-xs text-muted-foreground file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-secondary file:text-secondary-foreground hover:file:bg-secondary/80 cursor-pointer">
                </div>
            </div>
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
