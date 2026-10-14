@extends('layouts.public', ['title' => 'Session expired · ifotoset'])

@section('content')
<x-errors.page code="419" icon="clock" title="Your session expired" message="For your security, forms expire after a period of inactivity. Refresh the page and try again. Nothing has been lost.">
    <button type="button" onclick="location.reload()" class="btn btn-primary btn-lg"><x-icon name="refresh-cw" /> Refresh page</button>
    <a href="{{ url('/') }}" class="btn btn-outline btn-lg">Return home</a>
</x-errors.page>
@endsection
