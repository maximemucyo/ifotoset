@extends('layouts.guest', ['title' => 'Create your studio · ifotoset'])

@php
    // After a failed submit, reopen the step that holds the problem
    $startStep = $errors->has('email') || !old('email') ? 1 : 2;
@endphp

@section('heading')
<div x-data="{ step: {{ $startStep }} }" @signup-step.window="step = $event.detail">
    <h1 class="font-headline text-[32px] font-semibold leading-[1.05] tracking-[-0.02em] text-foreground [font-stretch:90%] sm:text-[36px]"
        x-text="step === 1 ? 'Create your studio' : 'Almost there'">{{ $startStep === 1 ? 'Create your studio' : 'Almost there' }}</h1>
    <p class="mt-3 text-[16px] leading-relaxed text-muted-foreground"
       x-text="step === 1 ? 'Start free. No credit card required.' : 'A couple of details to finish creating your account.'">{{ $startStep === 1 ? 'Start free. No credit card required.' : 'A couple of details to finish creating your account.' }}</p>
</div>
@endsection

@section('content')
<form method="POST" action="{{ route('register') }}" class="space-y-4"
      x-data="signupForm({ step: {{ $startStep }}, emailError: @js($errors->first('email')), checkUrl: @js(route('register.check-email')) })"
      @submit="onSubmit($event)">
    @csrf

    {{-- Step 1: how they sign up --}}
    <div class="space-y-4" x-show="step === 1" @if($startStep !== 1) x-cloak @endif>
        @include('auth.partials.google-button', ['label' => 'Sign up with Google'])

        <div>
            <label for="email" class="label">Work email</label>
            <input type="email" name="email" id="email" value="{{ old('email') }}" required autocomplete="username email"
                   placeholder="you@studio.com" class="input"
                   x-ref="email" x-model="email" @input="emailError = ''" @keydown.enter.prevent="continueWithEmail()"
                   :class="emailError && 'input-error'" :aria-invalid="(!!emailError).toString()" aria-describedby="email-error">
            <p id="email-error" class="error-text" x-show="emailError" x-text="emailError" @unless($errors->has('email')) x-cloak @endunless>{{ $errors->first('email') }}</p>
        </div>

        <button type="button" class="btn btn-primary btn-lg w-full" @click="continueWithEmail()" :disabled="checking">
            <x-icon name="loader" class="animate-spin" x-show="checking" x-cloak />
            <span>Continue with email</span>
        </button>

        <p class="border-t border-border pt-3 text-[15px] text-muted-foreground">
            Already have an account?
            <a href="{{ route('login') }}" class="link inline-flex min-h-11 items-center">Sign in</a>
        </p>
    </div>

    {{-- Step 2: the studio details --}}
    <div class="space-y-4" x-show="step === 2" @if($startStep !== 2) x-cloak @endif>
        <div class="flex items-center gap-2.5 rounded-lg border border-border bg-muted/50 py-1 pl-3 pr-1 text-[13px] text-muted-foreground">
            <x-icon name="mail" class="size-4 shrink-0" />
            <span class="min-w-0 flex-1 truncate">Signing up as <span class="font-medium text-foreground" x-text="email">{{ old('email') }}</span></span>
            <button type="button" class="link inline-flex min-h-11 shrink-0 items-center px-2" @click="changeEmail()">Change</button>
        </div>

        <x-ui.input label="Full name"
                    type="text"
                    name="name"
                    id="name"
                    :value="old('name')"
                    required
                    :autofocus="$startStep === 2"
                    autocomplete="name"
                    placeholder="Alex Morgan"
                    x-ref="name"
                    :error="$errors->first('name')" />

        <x-studio-url-input :value="old('username', '')" />

        <x-ui.phone-input label="Phone number"
                          required
                          placeholder="788 123 456"
                          hint="For mobile money payments and help with your account."
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
    </div>
</form>
@endsection
