@extends('layouts.app', ['title' => 'Settings · ' . $gallery->title . ' · ifotoset', 'workspace' => true])

@section('content')
<div class="flex min-h-0 flex-1 flex-col" x-data="{ coverThumb: null, totalPhotos: {{ (int) $gallery->photo_count }} }">
    <x-studio.gallery-workspace :gallery="$gallery" active="settings">
        @include('studio.partials.gallery-form', ['gallery' => $gallery])
    </x-studio.gallery-workspace>
</div>
@endsection
