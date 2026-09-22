@extends('layouts.public', [
    'seo' => $seo ?? null,
])

@section('content')
<div class="py-12 sm:py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Profile Header -->
        <div class="flex flex-col md:flex-row items-center md:items-start gap-8 pb-12 border-b border-border text-center md:text-left">
            <div class="w-28 h-28 sm:w-36 sm:h-36 rounded-2xl bg-primary/10 border-2 border-primary/20 flex items-center justify-center font-bold text-4xl text-primary overflow-hidden shadow-lg shrink-0">
                @if($photographer->avatar_url)
                    <img src="{{ $photographer->avatar_url }}" alt="{{ $photographer->name }}" class="w-full h-full object-cover">
                @else
                    {{ strtoupper(substr($photographer->name, 0, 1)) }}
                @endif
            </div>

            <div class="flex-1">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h1 class="text-3xl sm:text-4xl font-bold tracking-tight text-foreground">{{ $photographer->name }}</h1>
                        <p class="text-sm text-primary font-medium mt-1">@<span>{{ $photographer->username }}</span></p>
                    </div>

                    @if($packages->isNotEmpty())
                        <x-ui.button @click="$dispatch('open-modal', 'booking-modal')" variant="primary" size="lg">
                            Request a Booking
                        </x-ui.button>
                    @endif
                </div>

                @if($photographer->bio)
                    <p class="mt-4 text-base text-muted-foreground max-w-2xl leading-relaxed">
                        {{ $photographer->bio }}
                    </p>
                @endif

                <div class="mt-6 flex flex-wrap items-center justify-center md:justify-start gap-4 text-xs text-muted-foreground">
                    @if($photographer->location)
                        <span class="flex items-center gap-1.5 bg-card px-3 py-1.5 rounded-lg border border-border">
                            📍 {{ $photographer->location }}
                        </span>
                    @endif
                    @if($photographer->website)
                        <a href="{{ $photographer->website }}" target="_blank" rel="noopener noreferrer" class="flex items-center gap-1.5 bg-card px-3 py-1.5 rounded-lg border border-border hover:text-primary transition-colors">
                            🔗 {{ parse_url($photographer->website, PHP_URL_HOST) ?? $photographer->website }}
                        </a>
                    @endif
                    <span class="flex items-center gap-1.5 bg-card px-3 py-1.5 rounded-lg border border-border">
                        📸 {{ $galleries->count() }} Public {{ Str::plural('Gallery', $galleries->count()) }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Featured Galleries Section -->
        <div class="py-12">
            <h2 class="text-2xl font-bold tracking-tight text-foreground mb-8">Client Collections</h2>

            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-8">
                @forelse($galleries as $gallery)
                    @php
                        $coverUrl = $gallery->getCoverUrl('md');
                    @endphp
                    <a href="{{ $gallery->public_url }}"
                       class="group rounded-2xl overflow-hidden bg-card border border-border shadow-sm hover:shadow-xl hover:border-primary/50 transition-all duration-300 flex flex-col">
                        <div class="aspect-[16/10] w-full overflow-hidden bg-muted relative">
                            @if($coverUrl)
                                <img src="{{ $coverUrl }}"
                                     alt="{{ $gallery->title }}"
                                     loading="lazy"
                                     class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105">
                            @else
                                <div class="w-full h-full flex items-center justify-center text-muted-foreground text-sm font-medium">
                                    No cover image
                                </div>
                            @endif
                            <div class="absolute inset-0 bg-gradient-to-t from-foreground/60 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity"></div>
                        </div>

                        <div class="p-5 flex-1 flex flex-col justify-between">
                            <div>
                                <h3 class="text-lg font-bold text-foreground group-hover:text-primary transition-colors line-clamp-1">
                                    {{ $gallery->title }}
                                </h3>
                                @if($gallery->event_date)
                                    <p class="text-xs text-muted-foreground mt-1">
                                        {{ \Carbon\Carbon::parse($gallery->event_date)->format('F j, Y') }}
                                    </p>
                                @endif
                            </div>

                            <div class="mt-4 pt-3 border-t border-border/60 flex items-center justify-between text-xs text-muted-foreground">
                                <span>{{ $gallery->photo_count }} Photos</span>
                                <span class="font-semibold text-primary group-hover:translate-x-0.5 transition-transform">View Gallery &rarr;</span>
                            </div>
                        </div>
                    </a>
                @empty
                    <div class="col-span-3 text-center py-12 text-muted-foreground">
                        <p class="text-base font-medium">No public galleries available yet.</p>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Pricing Packages Section -->
        @if($packages->isNotEmpty())
            <div class="py-12 border-t border-border">
                <h2 class="text-2xl font-bold tracking-tight text-foreground mb-8">Investment & Packages</h2>

                <div class="grid md:grid-cols-3 gap-8">
                    @foreach($packages as $pkg)
                        <div class="rounded-2xl border border-border bg-card p-6 flex flex-col justify-between shadow-sm">
                            <div>
                                <div class="flex items-center justify-between">
                                    <h3 class="text-xl font-bold text-foreground">{{ $pkg->name }}</h3>
                                    <span class="text-xs font-semibold px-2.5 py-1 rounded bg-secondary text-secondary-foreground">
                                        {{ $pkg->duration_minutes }} mins
                                    </span>
                                </div>

                                <div class="mt-4 text-3xl font-bold tracking-tight text-foreground">
                                    {{ $pkg->currency }} {{ number_format($pkg->price, 0) }}
                                </div>

                                @if($pkg->description)
                                    <p class="mt-3 text-xs text-muted-foreground leading-relaxed">
                                        {{ $pkg->description }}
                                    </p>
                                @endif

                                @if(!empty($pkg->deliverables))
                                    <ul class="mt-4 space-y-2 text-xs text-muted-foreground">
                                        @foreach($pkg->deliverables as $item)
                                            <li class="flex items-center gap-2">
                                                <span class="text-primary font-bold">&check;</span> {{ $item }}
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>

                            <div class="mt-6">
                                <x-ui.button @click="$dispatch('open-modal', 'booking-modal'); document.querySelector('#package_id').value = '{{ $pkg->uuid }}'"
                                             variant="outline"
                                             class="w-full">
                                    Book This Package
                                </x-ui.button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>

<!-- Booking Modal -->
<x-ui.modal name="booking-modal" title="Request a Session Booking" maxWidth="lg">
    <form method="POST" action="{{ route('public.photographer.book', ['username' => $photographer->username]) }}" class="space-y-4">
        @csrf

        <x-ui.input label="Your Full Name"
                    name="client_name"
                    required
                    placeholder="Jane Smith" />

        <x-ui.input label="Email Address"
                    type="email"
                    name="client_email"
                    required
                    placeholder="jane@example.com" />

        <x-ui.input label="Phone / WhatsApp Number"
                    type="tel"
                    name="client_phone"
                    placeholder="+250 788 000 000" />

        <div class="space-y-1.5">
            <label for="package_id" class="block text-sm font-medium text-foreground">Select Package</label>
            <select name="package_id" id="package_id" required class="block w-full rounded-lg border border-border bg-input px-3.5 py-2 text-foreground text-sm shadow-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary">
                @foreach($packages as $pkg)
                    <option value="{{ $pkg->uuid }}">{{ $pkg->name }} ({{ $pkg->currency }} {{ number_format($pkg->price, 0) }})</option>
                @endforeach
            </select>
        </div>

        <x-ui.input label="Preferred Date & Time"
                    type="datetime-local"
                    name="starts_at"
                    required />

        <div class="space-y-1.5">
            <label for="notes" class="block text-sm font-medium text-foreground">Event Notes & Details</label>
            <textarea name="notes" id="notes" rows="3" class="block w-full rounded-lg border border-border bg-input px-3.5 py-2 text-foreground text-sm shadow-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary" placeholder="Tell us about the location, vibe, or special requests..."></textarea>
        </div>

        <div class="pt-2 flex justify-end gap-3">
            <x-ui.button @click="$dispatch('close-modal', 'booking-modal')" variant="outline">
                Cancel
            </x-ui.button>
            <x-ui.button type="submit" variant="primary">
                Submit Request
            </x-ui.button>
        </div>
    </form>
</x-ui.modal>
@endsection
