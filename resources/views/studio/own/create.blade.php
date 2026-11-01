@extends('layouts.app', ['title' => 'Create your studio · ifotoset', 'containerClass' => 'max-w-2xl'])

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
      x-data="{ submitting: false }"
      @submit="submitting = true">
    @csrf
    <div class="card-body space-y-5">
        <x-studio-url-input :value="old('username', '')" autofocus />
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
