@extends('layouts.marketing')

@php
    $faq = [
        [
            'question' => 'How do clients book a session?',
            'answer' => 'Share your booking link or let clients find it on your portfolio. They pick a package, choose an open time and leave their name, phone and email. You get the request in your studio and confirm it with one click.',
        ],
        [
            'question' => 'Can clients pay a deposit with Mobile Money?',
            'answer' => 'Yes, on the Professional plan and above. Set a deposit on any package, and clients pay it from their phone with MTN MoMo or Airtel Money when they book. The time slot is held while they pay, and the booking is confirmed automatically once the payment goes through.',
        ],
        [
            'question' => 'Can two clients book the same time?',
            'answer' => 'No. Booked and held slots disappear from the calendar straight away, and each package can add buffer time after a session so you have time to travel or rest. Cancelled bookings free their slot again.',
        ],
        [
            'question' => 'What about clients who call me or message on WhatsApp?',
            'answer' => 'Add the booking yourself from the studio. It goes into the same list and blocks the same time, so your calendar stays complete.',
        ],
        [
            'question' => 'Is online booking included in the Free plan?',
            'answer' => 'Yes. Every plan includes packages, your availability calendar and a public booking page. Mobile Money deposits start on Professional.',
        ],
    ];
@endphp

@section('page')
    <x-marketing.hero eyebrow="Online booking" description="Publish your packages and working hours once. Clients choose an open time from your booking page, pay their deposit from their phone and get a confirmation, while you stay behind the camera.">
        Photography bookings, with deposits paid by <em class="font-serif font-normal italic tracking-[-0.01em] text-brand">Mobile Money</em>.

        <x-slot:media>
            <x-marketing.screenshot name="booking-slots" :width="1200" :height="1168" priority url="yourstudio.ifotoset.com/book" class="mx-auto max-w-3xl" alt="The booking page of a photography studio on ifotoset: a calendar for October with open days, and the free start times for the selected day" />
        </x-slot:media>
    </x-marketing.hero>

    <section class="py-24 sm:py-32">
        <div class="mx-auto max-w-7xl space-y-24 px-4 sm:space-y-32 sm:px-6 lg:px-8">
            <x-marketing.feature-row
                eyebrow="For your clients"
                title="Three steps, from any phone"
                :points="[
                    'Pick a package with its price, length and deposit',
                    'Choose an open date and time, shown in your timezone',
                    'Leave a name, phone number and email',
                ]"
            >
                <p>No more back-and-forth on WhatsApp to find a date. Your booking page only offers times when you are actually free, based on your working hours and the sessions already booked.</p>
                <p>After booking, clients get an email and a page where they can check the status. Once you confirm, they get a calendar file to add the session to their phone, and both of you get a reminder by email the day before.</p>

                <x-slot:media>
                    <div class="relative flex justify-center rounded-3xl border border-border bg-muted/40 px-6 py-10 sm:py-14">
                        <div aria-hidden="true" class="absolute inset-0 rounded-3xl bg-dots opacity-60 mask-radial"></div>
                        <x-marketing.phone name="booking-mobile" class="relative" alt="The booking page on a phone, listing four packages with their price in RWF and their Mobile Money deposit" />
                    </div>
                </x-slot:media>
            </x-marketing.feature-row>

            <x-marketing.feature-row
                reverse
                eyebrow="For you"
                title="Every session in one list"
                :points="[
                    'Confirm or decline requests in one click',
                    'Add bookings that come in by phone or WhatsApp',
                    'Track pending, confirmed and completed sessions',
                    'See booked revenue at a glance',
                ]"
            >
                <p>New requests arrive in your studio with the client's details, the package and the chosen time. Confirm them, mark sessions as completed after the shoot, and keep the full history for each client.</p>

                <x-slot:media>
                    <x-marketing.screenshot name="booking-studio" url="ifotoset.com/studio/bookings" alt="The bookings list in the ifotoset studio, with counts of pending, confirmed and completed sessions, booked revenue in RWF, and each booking's client, package, date and status" />
                </x-slot:media>
            </x-marketing.feature-row>

            <x-marketing.feature-row
                eyebrow="Availability"
                title="Your hours, your rules"
                :points="[
                    'Weekly working hours for each day',
                    'Date exceptions for holidays and special days',
                    'Blocked time for personal plans',
                    'Buffer time after each package',
                ]"
            >
                <p>Set the days and hours you work, and choose whether clients see start times every 15 minutes, 30 minutes or every hour. Block out a wedding weekend or a trip in seconds.</p>

                <x-slot:media>
                    <x-marketing.screenshot name="booking-availability" url="ifotoset.com/studio/availability" alt="The availability settings in the ifotoset studio: working hours from Monday to Saturday, the Africa/Kigali timezone and a 30-minute slot interval" />
                </x-slot:media>
            </x-marketing.feature-row>
        </div>
    </section>

    <section class="border-y border-border bg-muted/40 py-24 sm:py-32">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <x-landing.section-heading eyebrow="Deposits" description="Available on Professional and Business. Clients pay with MTN MoMo or Airtel Money, without leaving your booking page.">
                Stop chasing <em class="font-serif font-normal italic tracking-normal">deposits</em>.
            </x-landing.section-heading>

            <ol class="mt-14 grid gap-4 md:grid-cols-3">
                @foreach([
                    ['icon' => 'package', 'title' => 'Set a deposit', 'body' => 'Choose how much clients pay upfront for each package, for example 30% of a wedding day.'],
                    ['icon' => 'smartphone', 'title' => 'Client pays on their phone', 'body' => 'They enter their Mobile Money number and approve the payment. Their time slot is held while they pay.'],
                    ['icon' => 'circle-check', 'title' => 'Booking confirmed', 'body' => 'As soon as the payment goes through, the booking is confirmed and both of you get an email.'],
                ] as $step)
                    <li class="card rounded-2xl p-6" data-reveal style="--reveal-delay: {{ $loop->index * 80 }}ms">
                        <div class="flex items-center justify-between">
                            <span class="inline-flex size-9 items-center justify-center rounded-lg bg-brand/10 text-brand"><x-icon :name="$step['icon']" /></span>
                            <span class="font-display text-[13px] font-semibold text-subtle tabular-nums">0{{ $loop->iteration }}</span>
                        </div>
                        <h3 class="mt-4 text-[16px] font-semibold tracking-tight">{{ $step['title'] }}</h3>
                        <p class="mt-1.5 text-[14px] leading-relaxed text-muted-foreground">{{ $step['body'] }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    <x-landing.faq :items="$faq" description="Common questions about taking bookings and deposits with ifotoset." />
    <x-marketing.related current="booking" />
@endsection
