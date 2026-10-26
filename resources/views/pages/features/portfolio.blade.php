@extends('layouts.marketing')

@php
    $faq = [
        [
            'question' => 'What address does my portfolio get?',
            'answer' => 'Your portfolio lives at yourname.ifotoset.com, using the username you choose when you sign up. Custom domains such as yourstudio.com are coming soon on the Professional plan.',
        ],
        [
            'question' => 'Do I need a designer or developer?',
            'answer' => 'No. Pick a template, upload your logo and cover, choose a font and a brand colour, and decide which galleries to feature. Changes go live as soon as you save.',
        ],
        [
            'question' => 'Will my portfolio show up on Google?',
            'answer' => 'Public portfolios are listed in our sitemap and include the page titles, descriptions and structured data search engines look for. You can write your own SEO title, description and share image for links on WhatsApp and social media.',
        ],
        [
            'question' => 'Can I show my prices?',
            'answer' => 'Yes. Your packages appear on your portfolio with their price, length and what is included, and each one links straight to your booking page.',
        ],
        [
            'question' => 'Which templates are included?',
            'answer' => 'There are four templates: Editorial, Minimal, Bold and Grid. Editorial and Minimal are available on every plan, and Bold and Grid come with paid plans. Every template shows the same content, so you can switch at any time.',
        ],
    ];
@endphp

@section('page')
    <x-marketing.hero eyebrow="Portfolio website" description="Show your best work on a fast, mobile-friendly website at yourname.ifotoset.com. Your galleries, packages and booking page are already connected, so a visitor can go from browsing to booking in a minute.">
        A photography portfolio website that <em class="font-serif font-normal italic tracking-[-0.01em] text-brand">books</em> sessions for you.

        <x-slot:media>
            <x-marketing.screenshot name="portfolio-home" priority url="yourstudio.ifotoset.com" alt="A photographer's portfolio website on ifotoset with the studio name, a short bio, location, Book a session and View work buttons, and a collage of recent work" />
        </x-slot:media>
    </x-marketing.hero>

    <section class="py-24 sm:py-32">
        <div class="mx-auto max-w-7xl space-y-24 px-4 sm:space-y-32 sm:px-6 lg:px-8">
            <x-marketing.feature-row
                eyebrow="Builder"
                title="Make it yours in an afternoon"
                :points="[
                    'Four templates you can switch between at any time',
                    'Your logo, cover photo, fonts and brand colour',
                    'Sections you can reorder, rename or hide',
                    'Curated collections, grouped by category',
                ]"
            >
                <p>Choose a template, add your branding and pick the galleries you want to show. Your portfolio updates as you deliver new work, so it never goes stale.</p>

                <x-slot:media>
                    <x-marketing.screenshot name="portfolio-builder" url="ifotoset.com/studio/portfolio" alt="The portfolio builder in the ifotoset studio, with settings for design, branding, sections, collections, about and reviews, social links and SEO, and a choice of templates" />
                </x-slot:media>
            </x-marketing.feature-row>

            <x-marketing.feature-row
                reverse
                eyebrow="Packages"
                title="Prices clients can book"
                :points="[
                    'Packages with price, length and what is included',
                    'A Book this package button on every card',
                    'Booking page and contact form built in',
                ]"
            >
                <p>Clients want to know what a session costs before they message you. Show your packages on your portfolio and let them book the one that fits, without leaving your site.</p>

                <x-slot:media>
                    <x-marketing.screenshot name="portfolio-packages" url="yourstudio.ifotoset.com/#packages" alt="The packages section of a portfolio: Portrait Session, Engagement Story and Wedding Day with prices in RWF, what each includes and a Book this package button" />
                </x-slot:media>
            </x-marketing.feature-row>

            <x-marketing.feature-row
                eyebrow="Mobile"
                title="Looks right on every phone"
                :points="[
                    'About page with your story and client testimonials',
                    'Contact page and a WhatsApp button',
                    'Links to Instagram and your other profiles',
                ]"
            >
                <p>Most visitors will find you from Instagram or WhatsApp, on their phone. Every template is designed for small screens first and loads quickly on mobile data.</p>

                <x-slot:media>
                    <div class="relative flex justify-center rounded-3xl border border-border bg-muted/40 px-6 py-10 sm:py-14">
                        <div aria-hidden="true" class="absolute inset-0 rounded-3xl bg-dots opacity-60 mask-radial"></div>
                        <x-marketing.phone name="portfolio-mobile" class="relative" alt="A photographer's portfolio website on a phone" />
                    </div>
                </x-slot:media>
            </x-marketing.feature-row>
        </div>
    </section>

    <section class="border-y border-border bg-muted/40 py-24 sm:py-32">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <x-landing.section-heading eyebrow="Found online" description="The technical work that helps new clients find you is already done.">
                Built to be <em class="font-serif font-normal italic tracking-normal">found</em>.
            </x-landing.section-heading>

            <div class="mt-14 grid gap-4 md:grid-cols-3">
                @foreach([
                    ['icon' => 'search', 'title' => 'Search-friendly', 'body' => 'Clean page titles, descriptions and structured data on every page, and your public work is listed in our sitemap.'],
                    ['icon' => 'share-2', 'title' => 'Good-looking links', 'body' => 'Choose the image and text people see when your link is shared on WhatsApp, Instagram or Facebook.'],
                    ['icon' => 'zap', 'title' => 'Fast everywhere', 'body' => 'Photos are resized for each screen, so your portfolio opens quickly even on a slow connection.'],
                ] as $item)
                    <article class="card rounded-2xl p-6" data-reveal style="--reveal-delay: {{ $loop->index * 80 }}ms">
                        <span class="inline-flex size-9 items-center justify-center rounded-lg bg-brand/10 text-brand"><x-icon :name="$item['icon']" /></span>
                        <h3 class="mt-4 text-[16px] font-semibold tracking-tight">{{ $item['title'] }}</h3>
                        <p class="mt-1.5 text-[14px] leading-relaxed text-muted-foreground">{{ $item['body'] }}</p>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    <x-landing.faq :items="$faq" description="Common questions about building your portfolio on ifotoset." />
    <x-marketing.related current="portfolio" />
@endsection
