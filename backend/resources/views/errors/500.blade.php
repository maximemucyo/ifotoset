@extends('layouts.public', ['title' => 'Something went wrong · ifotoset'])

@section('content')
<x-errors.page code="500" icon="triangle-alert" title="Something went wrong" message="We hit an unexpected error while processing your request. Please try again in a moment.">
    <button type="button" onclick="location.reload()" class="btn btn-primary btn-lg"><x-icon name="refresh-cw" /> Try again</button>
    <a href="{{ url('/') }}" class="btn btn-outline btn-lg">Back to home</a>
    <x-slot:footer>
        Still stuck? <a href="mailto:support@ifotoset.com" class="link">Contact support</a>
    </x-slot:footer>
</x-errors.page>
@endsection
