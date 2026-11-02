@extends('layouts.guest', ['title' => 'Create your studio · ifotoset', 'heading' => 'Create your studio', 'subheading' => 'Start free. No credit card required.'])

@section('content')
<form method="POST" action="{{ route('register') }}" class="space-y-4"
      x-data="{
          submitting: false,
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

    <x-studio-url-input :value="old('username', '')" />

    <x-ui.input label="Work email"
                type="email"
                name="email"
                id="email"
                :value="old('email')"
                required
                autocomplete="username email"
                placeholder="you@studio.com"
                :error="$errors->first('email')" />

    <x-ui.phone-input label="Phone number"
                      required
                      placeholder="788 123 456"
                      :error="$errors->first('phone')" />

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
            <span class="w-16 text-right text-[12px] font-medium text-muted-foreground" x-text="strengthLabel"></span>
        </div>
    </div>

    <button type="submit" class="btn btn-primary btn-lg w-full !mt-6" :disabled="submitting">
        <x-icon name="loader" class="animate-spin" x-show="submitting" x-cloak />
        <span>Create studio</span>
    </button>

    <p class="text-[13px] leading-relaxed text-muted-foreground">
        By creating an account you agree to our <a href="{{ route('legal.terms') }}" class="link">Terms of Service</a> and <a href="{{ route('legal.privacy') }}" class="link">Privacy Policy</a>.
    </p>

    <p class="border-t border-border pt-3 text-[15px] text-muted-foreground">
        Already have an account?
        <a href="{{ route('login') }}" class="link inline-flex min-h-11 items-center">Sign in</a>
    </p>
</form>
@endsection
