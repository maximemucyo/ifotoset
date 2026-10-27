@extends('layouts.guest', ['title' => 'Create your studio · ifotoset', 'heading' => 'Almost there', 'subheading' => 'A couple of details to finish creating your account.'])

@php
    $rootHost = config('app.public_root_host', 'ifotoset.com');
@endphp

@section('content')
<form method="POST" action="{{ route('auth.google.complete') }}" class="space-y-4"
      x-data="{
          submitting: false,
          username: @js(old('username', $suggestedUsername)),
          slugify(v) { return v.toLowerCase().replace(/[^a-z0-9-]/g, '').slice(0, 50); },
      }"
      @submit="submitting = true">
    @csrf

    <div class="flex items-center gap-2.5 rounded-lg border border-border bg-muted/50 px-3 py-2.5 text-[13px] text-muted-foreground">
        <x-icon name="circle-check" class="size-4 text-success" />
        <span>Signed in with Google as <span class="font-medium text-foreground">{{ $profile['email'] }}</span></span>
    </div>

    <x-ui.input label="Full name"
                type="text"
                name="name"
                id="name"
                :value="old('name', $profile['name'])"
                required
                autocomplete="name"
                placeholder="Alex Morgan"
                :error="$errors->first('name')" />

    <div>
        <label for="username" class="label">Studio URL</label>
        <div class="flex items-stretch overflow-hidden rounded-lg border border-border bg-input shadow-xs transition focus-within:border-ring focus-within:ring-4 focus-within:ring-ring/15 {{ $errors->has('username') ? '!border-destructive' : '' }}">
            <input type="text" name="username" id="username" required autofocus autocomplete="username" placeholder="yourstudio"
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

    <x-ui.phone-input label="Phone number"
                      required
                      placeholder="788 123 456"
                      :error="$errors->first('phone')" />

    <button type="submit" class="btn btn-primary btn-lg w-full !mt-6" :disabled="submitting">
        <x-icon name="loader" class="animate-spin" x-show="submitting" x-cloak />
        <span>Create studio</span>
        <x-icon name="arrow-right" x-show="!submitting" />
    </button>

    <p class="text-center text-xs leading-relaxed text-muted-foreground">
        By creating an account you agree to our <a href="{{ route('legal.terms') }}" class="link">Terms of Service</a> and <a href="{{ route('legal.privacy') }}" class="link">Privacy Policy</a>.
    </p>

    <p class="border-t border-border pt-5 text-center text-[13px] text-muted-foreground">
        Not you?
        <a href="{{ route('auth.google') }}" class="link">Use a different Google account</a>
    </p>
</form>
@endsection
