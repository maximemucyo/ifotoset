@extends('layouts.app', ['title' => 'New gallery · ifotoset'])

@section('content')
<div class="mx-auto max-w-5xl">
    <x-ui.page-header title="New gallery"
                      description="Set up a collection to deliver photos to your client. You'll upload photos next."
                      :back="route('studio.galleries.index')" back-label="Galleries" />

    <form method="POST" action="{{ route('studio.galleries.store') }}" enctype="multipart/form-data"
          x-data="{ visibility: '{{ old('visibility', 'public') }}', submitting: false }" @submit="submitting = true">
        @csrf

        @include('studio.partials.gallery-form', ['gallery' => null, 'invitations' => null])

        <div class="sticky bottom-3 z-20 mt-8 rounded-xl border border-border bg-card/90 px-3 py-2.5 shadow-lg backdrop-blur-md sm:bottom-4 sm:px-4">
            <div class="flex items-center justify-between gap-3">
                <p class="hidden text-[13px] text-muted-foreground sm:block">You can change all of these settings later.</p>
                <div class="flex flex-1 items-center justify-end gap-2">
                    <a href="{{ route('studio.galleries.index') }}" class="btn btn-ghost">Cancel</a>
                    <button type="submit" class="btn btn-primary" :disabled="submitting">
                        <x-icon name="loader-circle" class="animate-spin" x-show="submitting" x-cloak />
                        Create gallery
                        <x-icon name="arrow-right" x-show="!submitting" />
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
