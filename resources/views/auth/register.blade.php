@extends('layouts.guest', ['title' => 'Create your studio · ifotoset', 'heading' => 'Create your studio', 'subheading' => 'Start free. No credit card required.'])

@php
    $rootHost = config('app.public_root_host', 'ifotoset.com');
@endphp

@section('content')
<form method="POST" action="{{ route('register') }}" class="space-y-4"
      x-data="{
          submitting: false,
          username: @js(old('username', '')),
          password: '',
          get strength() {
              let s = 0;
              if (this.password.length >= 8) s++;
              if (/[A-Z]/.test(this.password) && /[a-z]/.test(this.password)) s++;
              if (/\d/.test(this.password)) s++;
              if (/[^A-Za-z0-9]/.test(this.password) || this.password.length >= 14) s++;
              return s;
          },
          get strengthLabel() { return ['Too short', 'Weak', 'Fair', 'Good', 'Strong'][this.strength]; },
          slugify(v) { return v.toLowerCase().replace(/[^a-z0-9-]/g, '').slice(0, 50); },
      }"
      @submit="submitting = true">
    @csrf

    @include('auth.partials.google-button', ['label' => 'Sign up with Google'])

    <x-ui.input label="Full name"
                type="text"
                name="name"
                id="name"
                :value="old('name')"
                required
                autofocus
                autocomplete="name"
                placeholder="Alex Morgan"
                :error="$errors->first('name')" />

    <div>
        <label for="username" class="label">Studio URL</label>
        <div class="flex items-stretch overflow-hidden rounded-lg border border-border bg-input shadow-xs transition focus-within:border-ring focus-within:ring-4 focus-within:ring-ring/15 {{ $errors->has('username') ? '!border-destructive' : '' }}">
            <input type="text" name="username" id="username" required autocomplete="username" placeholder="yourstudio"
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

    <x-ui.input label="Work email"
                type="email"
                name="email"
                id="email"
                :value="old('email')"
                required
                autocomplete="email"
                placeholder="you@studio.com"
                :error="$errors->first('email')" />

    <div>
        <x-ui.input label="Password"
                    type="password"
                    name="password"
                    id="password"
                    required
                    autocomplete="new-password"
                    placeholder="At least 8 characters"
                    x-model="password"
                    :error="$errors->first('password')" />
        <div class="mt-2 flex items-center gap-2" x-show="password.length" x-cloak>
            <div class="grid flex-1 grid-cols-4 gap-1">
                <template x-for="i in 4">
                    <span class="h-1 rounded-full transition-colors"
                          :class="i <= strength ? (strength <= 1 ? 'bg-destructive' : strength === 2 ? 'bg-warning' : 'bg-success') : 'bg-muted'"></span>
                </template>
            </div>
            <span class="w-14 text-right text-[11px] font-medium text-muted-foreground" x-text="strengthLabel"></span>
        </div>
    </div>

    <x-ui.input label="Confirm password"
                type="password"
                name="password_confirmation"
                id="password_confirmation"
                required
                autocomplete="new-password"
                placeholder="Repeat your password" />

    <button type="submit" class="btn btn-primary btn-lg w-full !mt-6" :disabled="submitting">
        <x-icon name="loader" class="animate-spin" x-show="submitting" x-cloak />
        <span>Create studio</span>
        <x-icon name="arrow-right" x-show="!submitting" />
    </button>

    <p class="text-center text-xs leading-relaxed text-muted-foreground">
        By creating an account you agree to our <a href="{{ route('legal.terms') }}" class="link">Terms of Service</a> and <a href="{{ route('legal.privacy') }}" class="link">Privacy Policy</a>.
    </p>

    <p class="border-t border-border pt-5 text-center text-[13px] text-muted-foreground">
        Already have an account?
        <a href="{{ route('login') }}" class="link">Sign in</a>
    </p>
</form>
@endsection
