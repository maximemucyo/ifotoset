@extends('layouts.public')

@section('content')
<div class="min-h-[70vh] flex flex-col items-center justify-center text-center px-4 py-16">
    <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-primary/10 text-primary mb-6 font-bold text-3xl">
        <svg class="w-10 h-10 animate-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
        </svg>
    </div>
    <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight text-foreground mb-3">
        Scheduled Maintenance
    </h1>
    <p class="text-muted-foreground text-base max-w-md mb-8">
        We are performing quick scheduled improvements to ensure optimal speed and reliability. We will be back in just a few minutes.
    </p>
    <div class="flex items-center justify-center">
        <a href="javascript:location.reload()" class="inline-flex items-center justify-center px-6 py-2.5 rounded-lg bg-primary text-primary-foreground font-semibold hover:opacity-90 transition">
            Check Again
        </a>
    </div>
</div>
@endsection
