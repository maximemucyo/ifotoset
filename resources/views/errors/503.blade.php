@extends('layouts.public', ['title' => 'Down for maintenance · ifotoset', 'robots' => 'noindex, nofollow'])

@section('content')
<x-errors.page code="503" icon="settings" title="Back in a few minutes" message="We're making some quick improvements to keep ifotoset fast and reliable. Your galleries and data are safe.">
    <button type="button" onclick="location.reload()" class="btn btn-primary btn-lg"><x-icon name="refresh-cw" /> Check again</button>
</x-errors.page>
@endsection
