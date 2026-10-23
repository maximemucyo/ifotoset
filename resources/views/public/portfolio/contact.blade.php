@extends('layouts.public', [
    'seo' => $seo ?? null,
    'hideNav' => true,
])

@php
    $contactSection = $portfolio->section('contact');
    $socialLinks = $portfolio->socialLinks();
    $whatsappUrl = $portfolio->whatsappUrl('Hi ' . $photographer->name . ', I found you on your portfolio.');
@endphp

@section('content')
@include('public.partials.photographer-header', ['hasPackages' => $hasPackages, 'current' => 'contact'])

<section class="relative">
    <div class="mx-auto max-w-6xl px-4 py-14 sm:px-6 sm:py-20 lg:px-8">
        <div class="grid gap-12 lg:grid-cols-12 lg:gap-16">
            <div class="{{ $portfolio->contactFormEnabled() ? 'lg:col-span-5' : 'lg:col-span-8 lg:col-start-3 text-center' }}">
                <p class="text-[11px] font-medium uppercase tracking-[0.28em] text-muted-foreground">Contact</p>
                <h1 class="pf-heading mt-3 text-5xl leading-[1.02] text-foreground sm:text-6xl">Get in touch</h1>
                <p class="mt-5 text-base leading-relaxed text-muted-foreground">
                    Planning a session, an event or a collaboration?
                    @if($hasPackages)
                        Send {{ $photographer->name }} a message or <a href="{{ route('subdomain.booking', ['username' => $photographer->username]) }}" class="link">book a session directly</a>.
                    @else
                        Send {{ $photographer->name }} a message.
                    @endif
                </p>

                <div class="mt-8 space-y-3 {{ $portfolio->contactFormEnabled() ? '' : 'mx-auto max-w-sm' }}">
                    @if($whatsappUrl)
                        <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener noreferrer" class="card card-hover flex items-center gap-3 p-4 text-left">
                            <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-[#25d366] text-white"><x-icon name="whatsapp" class="size-5" /></span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-medium text-foreground">Chat on WhatsApp</span>
                                <span class="block text-[13px] text-muted-foreground">Usually the fastest way to reach {{ Str::before($photographer->name, ' ') ?: $photographer->name }}</span>
                            </span>
                            <x-icon name="arrow-up-right" class="size-4 text-muted-foreground" />
                        </a>
                    @endif
                    @if($photographer->location)
                        <div class="flex items-center gap-3 p-1 text-left text-sm text-muted-foreground">
                            <x-icon name="map-pin" class="size-4" /> Based in {{ $photographer->location }}
                        </div>
                    @endif
                </div>

                @if($socialLinks)
                    <div class="mt-6 flex items-center gap-2 {{ $portfolio->contactFormEnabled() ? '' : 'justify-center' }}">
                        @foreach($socialLinks as $social)
                            @continue($social['network'] === 'whatsapp')
                            <a href="{{ $social['url'] }}" target="_blank" rel="noopener noreferrer me" aria-label="{{ $social['label'] }}"
                               class="flex size-10 items-center justify-center rounded-full border border-border text-muted-foreground transition hover:border-border-strong hover:text-foreground">
                                <x-icon :name="$social['icon']" class="size-4" />
                            </a>
                        @endforeach
                    </div>
                @endif

                @if(!$portfolio->contactFormEnabled() && !$whatsappUrl && !$socialLinks && !$hasPackages)
                    <p class="mt-8 text-sm text-muted-foreground">{{ $photographer->name }} isn't taking messages online right now.</p>
                @endif
            </div>

            @if($portfolio->contactFormEnabled())
                <div class="lg:col-span-7">
                    <form method="POST" action="{{ route('subdomain.contact.send', ['username' => $photographer->username]) }}" class="card p-6 sm:p-8">
                        @csrf
                        @if(session('success'))
                            <div class="mb-6 flex items-start gap-3 rounded-xl border border-success/30 bg-success/10 p-4 text-sm text-foreground" role="status">
                                <x-icon name="circle-check" class="mt-0.5 size-4 text-success" /> <span>{{ session('success') }}</span>
                            </div>
                        @endif
                        @if(session('error'))
                            <div class="mb-6 flex items-start gap-3 rounded-xl border border-destructive/30 bg-destructive/10 p-4 text-sm text-foreground" role="alert">
                                <x-icon name="circle-alert" class="mt-0.5 size-4 text-destructive" /> <span>{{ session('error') }}</span>
                            </div>
                        @endif

                        <div class="space-y-5">
                            <div class="grid gap-5 sm:grid-cols-2">
                                <x-ui.input label="Your name" name="name" required autocomplete="name" maxlength="120"
                                            :value="old('name')" :error="$errors->first('name')" />
                                <x-ui.input label="Email" name="email" type="email" required autocomplete="email"
                                            :value="old('email')" :error="$errors->first('email')" />
                            </div>
                            <x-ui.input label="Phone / WhatsApp (optional)" name="phone" type="tel" autocomplete="tel" placeholder="+250 788 000 000"
                                        :value="old('phone')" :error="$errors->first('phone')" />
                            <x-ui.textarea label="Message" name="message" required rows="6" maxlength="3000"
                                           placeholder="Tell {{ Str::before($photographer->name, ' ') ?: $photographer->name }} about the date, place and what you have in mind."
                                           :value="old('message')" :error="$errors->first('message')" />
                            {{-- Honeypot --}}
                            <div class="hidden" aria-hidden="true">
                                <label for="contact-website">Website</label>
                                <input type="text" id="contact-website" name="website" tabindex="-1" autocomplete="off">
                            </div>
                        </div>
                        <div class="mt-6 flex flex-col-reverse items-stretch gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <p class="text-xs text-muted-foreground">Your message goes straight to {{ $photographer->name }} by email.</p>
                            <button type="submit" class="btn btn-primary">Send message <x-icon name="send" /></button>
                        </div>
                    </form>
                </div>
            @endif
        </div>
    </div>
</section>

@include('public.partials.whatsapp-button')
@endsection

@section('footer')
    @include('public.partials.photographer-footer')
@endsection
