@extends('layouts.public', ['title' => 'Gallery removed · ifotoset', 'robots' => 'noindex, nofollow'])

@section('content')
<x-errors.page code="410" icon="images" title="This gallery has been removed" message="The photographer has taken this gallery down. If you still need these photos, ask them for a new link.">
    <a href="{{ url('/') }}" class="btn btn-primary btn-lg"><x-icon name="house" /> Back to home</a>
</x-errors.page>
@endsection
