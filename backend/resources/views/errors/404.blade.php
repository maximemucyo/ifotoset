@extends('layouts.public', ['title' => 'Page not found · ifotoset'])

@section('content')
<x-errors.page code="404" icon="search" title="This page is out of frame" message="The gallery, photo or page you're looking for may have been moved, deleted or never existed. Double-check the link you were sent.">
    <a href="{{ url('/') }}" class="btn btn-primary btn-lg"><x-icon name="house" /> Back to home</a>
    <button type="button" onclick="history.length > 1 ? history.back() : (location.href = '{{ url('/') }}')" class="btn btn-outline btn-lg"><x-icon name="arrow-left" /> Go back</button>
    <x-slot:footer>
        Looking for a client gallery? Ask your photographer for a fresh link.
    </x-slot:footer>
</x-errors.page>
@endsection
