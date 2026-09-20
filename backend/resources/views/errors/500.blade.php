@extends('layouts.public')

@section('content')
<div class="min-h-[70vh] flex flex-col items-center justify-center text-center px-4 py-16">
    <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-destructive/10 text-destructive mb-6 font-bold text-3xl">
        500
    </div>
    <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight text-foreground mb-3">
        Something Went Wrong
    </h1>
    <p class="text-muted-foreground text-base max-w-md mb-8">
        We encountered an unexpected error processing your request. Our team has been notified.
    </p>
    <div class="flex flex-wrap items-center justify-center gap-4">
        <a href="{{ url('/') }}" class="inline-flex items-center justify-center px-6 py-2.5 rounded-lg bg-primary text-primary-foreground font-semibold hover:opacity-90 transition">
            Back to Home
        </a>
        <a href="mailto:support@ifotoset.com" class="inline-flex items-center justify-center px-6 py-2.5 rounded-lg border border-border text-foreground hover:bg-muted/50 transition">
            Contact Support
        </a>
    </div>
</div>
@endsection
