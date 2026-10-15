@extends('layouts.public', ['title' => 'Too many requests · ifotoset', 'robots' => 'noindex, nofollow'])

@section('content')
<x-errors.page code="429" icon="timer" title="Slow down a little" message="You've made a lot of requests in a short time. Wait a minute, then try again.">
    <button type="button" onclick="location.reload()" class="btn btn-primary btn-lg"><x-icon name="refresh-cw" /> Try again</button>
    <button type="button" onclick="history.length > 1 ? history.back() : (location.href = '{{ url('/') }}')" class="btn btn-outline btn-lg"><x-icon name="arrow-left" /> Go back</button>
</x-errors.page>
@endsection
