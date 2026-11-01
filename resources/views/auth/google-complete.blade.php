@extends('layouts.guest', ['title' => 'Create your studio · ifotoset', 'heading' => 'Almost there', 'subheading' => 'A couple of details to finish creating your account.'])

@section('content')
<form method="POST" action="{{ route('auth.google.complete') }}" class="space-y-4"
      x-data="{ submitting: false }"
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

    <x-studio-url-input :value="old('username', $suggestedUsername)" autofocus />

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
