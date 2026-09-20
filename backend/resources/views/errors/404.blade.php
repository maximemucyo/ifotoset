@extends('layouts.public')

@section('content')
<div class="min-h-[70vh] flex flex-col items-center justify-center text-center px-4 py-16">
    <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-primary/10 text-primary mb-6 font-bold text-3xl">
        404
    </div>
    <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight text-foreground mb-3">
        Page Not Found
    </h1>
    <p class="text-muted-foreground text-base max-w-md mb-8">
        The gallery, photo, or page you are looking for might have been moved, deleted, or does not exist.
    </p>
    <div class="flex flex-wrap items-center justify-center gap-4">
        <a href="{{ url('/') }}" class="inline-flex items-center justify-center px-6 py-2.5 rounded-lg bg-primary text-primary-foreground font-semibold hover:opacity-90 transition">
            Back to Home
        </a>
        <a href="javascript:history.back()" class="inline-flex items-center justify-center px-6 py-2.5 rounded-lg border border-border text-foreground hover:bg-muted/50 transition">
            Go Back
        </a>
    </div>
</div>
@endsection
