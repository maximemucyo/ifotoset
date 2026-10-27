@extends('layouts.app', ['title' => 'Create your studio · ifotoset', 'containerClass' => 'max-w-2xl'])

@php
    $rootHost = config('app.public_root_host', 'ifotoset.com');
@endphp

@section('content')
<x-ui.page-header title="Create your own studio"
    description="Get your own portfolio, galleries and booking page on the Free plan, using the login you already have." />

@unless($hasTeam)
    <div class="alert alert-info mb-6">
        <x-icon name="info" />
        <div>
            <p class="font-medium">You're not part of a team studio right now.</p>
            <p class="mt-0.5 text-[13px]">Your team access has ended. Create a studio of your own to keep using ifotoset, or ask a studio owner to invite you again.</p>
        </div>
    </div>
@endunless

<form method="POST" action="{{ route('studio.own.store') }}" class="card"
      x-data="{ submitting: false, username: @js(old('username', '')), slugify(v) { return v.toLowerCase().replace(/[^a-z0-9-]/g, '').slice(0, 50); } }"
      @submit="submitting = true">
    @csrf
    <div class="card-body space-y-5">
        <div>
            <label for="username" class="label">Studio URL</label>
            <div class="flex items-stretch overflow-hidden rounded-lg border border-border bg-input shadow-xs transition focus-within:border-ring focus-within:ring-4 focus-within:ring-ring/15 {{ $errors->has('username') ? '!border-destructive' : '' }}">
                <input type="text" name="username" id="username" required autofocus autocomplete="off" spellcheck="false" placeholder="yourstudio"
                       x-model="username" @input="username = slugify($event.target.value)"
                       class="h-10 min-w-0 flex-1 bg-transparent px-3 text-sm text-foreground outline-none placeholder:text-subtle">
                <span class="flex items-center border-l border-border bg-muted px-3 text-[13px] text-muted-foreground">.{{ $rootHost }}</span>
            </div>
            @error('username')
                <p class="error-text">{{ $message }}</p>
            @else
                <p class="help">Your portfolio: <span class="font-medium text-foreground" x-text="(username || 'yourstudio') + '.{{ $rootHost }}'"></span></p>
            @enderror
        </div>
        <ul class="space-y-2 text-[13px] text-muted-foreground">
            <li class="flex gap-2"><x-icon name="check" class="mt-0.5 size-4 shrink-0 text-success" /> Your studio and the team studios you work in stay completely separate.</li>
            <li class="flex gap-2"><x-icon name="check" class="mt-0.5 size-4 shrink-0 text-success" /> Switch between them from the menu at the bottom of the sidebar.</li>
        </ul>
    </div>
    <div class="card-footer flex justify-end">
        <button type="submit" class="btn btn-primary" :disabled="submitting">
            <x-icon name="loader-circle" class="animate-spin" x-show="submitting" x-cloak />
            Create studio
        </button>
    </div>
</form>
@endsection
