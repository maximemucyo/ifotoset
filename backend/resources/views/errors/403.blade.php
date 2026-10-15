@extends('layouts.public', ['title' => 'Access restricted · ifotoset', 'robots' => 'noindex, nofollow'])

@section('content')
<x-errors.page code="403" icon="lock" title="Access restricted" :message="$exception->getMessage() ?: 'You don\'t have permission to view this page. If this is a private client gallery, open it from your gallery link and enter the password.'">
    <a href="{{ url('/') }}" class="btn btn-primary btn-lg"><x-icon name="house" /> Back to home</a>
    <button type="button" onclick="history.length > 1 ? history.back() : (location.href = '{{ url('/') }}')" class="btn btn-outline btn-lg"><x-icon name="arrow-left" /> Go back</button>
    @guest
        <x-slot:footer>
            Have an account? <a href="{{ route('login') }}" class="link">Sign in</a> to continue.
        </x-slot:footer>
    @endguest
</x-errors.page>
@endsection
