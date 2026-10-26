@extends('layouts.marketing')

@php
    $formatStorage = function (?int $bytes): string {
        $gb = (int) round(($bytes ?? 0) / 1024 ** 3);
        return $gb >= 1000 ? rtrim(rtrim(number_format($gb / 1000, 1), '0'), '.') . ' TB' : $gb . ' GB';
    };
    $formatDuration = function (int $seconds): ?string {
        if ($seconds <= 0) {
            return null;
        }
        return $seconds < 3600 ? round($seconds / 60) . ' min' : rtrim(rtrim(number_format($seconds / 3600, 1), '0'), '.') . ' h';
    };
    $formatPrice = fn ($amount, $currency) => (float) $amount > 0 ? $currency . ' ' . number_format((float) $amount, 0) : 'Free';

    // Each row resolves per plan to true (included), false (not included) or a short value.
    $groups = [
        'Price' => [
            'Monthly' => fn ($p) => $formatPrice($p->monthly_price, $p->currency),
            'Yearly' => fn ($p) => (float) $p->annual_price > 0 ? $formatPrice($p->annual_price, $p->currency) : ((float) $p->monthly_price > 0 ? false : 'Free'),
        ],
        'Galleries' => [
            'Client galleries' => fn ($p) => $p->gallery_limit ? (string) $p->gallery_limit : 'Unlimited',
            'Storage' => fn ($p) => $formatStorage($p->storage_limit),
            'Hosted video' => fn ($p) => $formatDuration((int) $p->video_limit_seconds) ?? false,
            'Longest single video' => fn ($p) => $formatDuration((int) $p->max_single_video_duration_seconds) ?? false,
            'Privacy, PINs and expiry dates' => fn ($p) => true,
            'Download controls and favourites' => fn ($p) => true,
        ],
        'Portfolio and booking' => [
            'Portfolio website on your subdomain' => fn ($p) => true,
            'Bold and Grid templates' => fn ($p) => in_array('premium_templates', $p->featureCodes(), true),
            'Online booking page' => fn ($p) => true,
            'Mobile Money booking deposits' => fn ($p) => in_array('booking_deposits', $p->featureCodes(), true),
            'Custom domain' => fn ($p) => in_array('custom_domain', $p->featureCodes(), true) ? 'Soon' : false,
        ],
        'Support' => [
            'Email support' => fn ($p) => true,
            'Priority support' => fn ($p) => in_array('priority_support', $p->featureCodes(), true),
        ],
    ];

    $faq = [
        [
            'question' => 'How do I pay for my plan?',
            'answer' => 'With Mobile Money, straight from your studio. Choose a plan, enter your MTN MoMo or Airtel Money number and approve the payment on your phone. Your plan is upgraded as soon as the payment goes through.',
        ],
        [
            'question' => 'Does my plan renew automatically?',
            'answer' => 'No. You pay for a month or a year at a time, and we email you before your plan ends so you can renew. Nothing is charged without your approval.',
        ],
        [
            'question' => 'Is the Free plan really free?',
            'answer' => 'Yes, with no card and no time limit. You get a portfolio website, a booking page, unlimited galleries and 2 GB of storage. Upgrade when you need more space, video or Mobile Money deposits.',
        ],
        [
            'question' => 'What happens if my paid plan ends?',
            'answer' => 'Your account moves to the Free plan. If you store more than the Free limit, uploads stop until you renew or free up space. After ' . config('billing.over_quota.restrict_after_days') . ' days new galleries and client downloads pause, after ' . config('billing.over_quota.archive_after_days') . ' days galleries go offline for clients, and after ' . config('billing.over_quota.delete_after_days') . ' days the oldest galleries are deleted until you are back within the limit. We email you before each step, and renewing lifts every restriction straight away.',
        ],
        [
            'question' => 'Can I change plans later?',
            'answer' => 'Yes. You can upgrade, downgrade or cancel at any time from Billing in your studio. There are no setup fees and no long-term contracts.',
        ],
    ];
@endphp

@section('page')
    <x-landing.pricing :plans="$plans" heading-tag="h1" />

    @if($plans->isNotEmpty())
        <section class="border-t border-border py-24 sm:py-32">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <x-landing.section-heading eyebrow="Compare plans" description="Every plan includes client galleries, a portfolio website and online booking. Paid plans add storage, video and Mobile Money deposits.">
                    What's in each <em class="font-serif font-normal italic tracking-normal">plan</em>.
                </x-landing.section-heading>

                <div class="mt-14 overflow-x-auto rounded-2xl border border-border bg-card" data-reveal>
                    <table class="w-full min-w-[640px] text-left text-[14px]">
                        <thead>
                            <tr class="border-b border-border">
                                <th scope="col" class="sticky left-0 w-[34%] bg-card px-5 py-4 font-medium text-muted-foreground"><span class="sr-only">Feature</span></th>
                                @foreach($plans as $plan)
                                    <th scope="col" class="px-4 py-4 text-center text-[15px] font-semibold tracking-tight text-foreground">{{ $plan->name }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        @foreach($groups as $group => $rows)
                            <tbody>
                                <tr class="bg-muted/50">
                                    <th scope="colgroup" colspan="{{ $plans->count() + 1 }}" class="px-5 py-2.5 text-[12px] font-semibold uppercase tracking-[0.08em] text-muted-foreground">{{ $group }}</th>
                                </tr>
                                @foreach($rows as $label => $resolve)
                                    <tr class="border-t border-border">
                                        <th scope="row" class="sticky left-0 bg-card px-5 py-3.5 font-normal text-foreground">{{ $label }}</th>
                                        @foreach($plans as $plan)
                                            @php $value = $resolve($plan); @endphp
                                            <td class="px-4 py-3.5 text-center tabular-nums">
                                                @if($value === true)
                                                    <x-icon name="check" class="mx-auto size-4.5 text-brand" /><span class="sr-only">Included</span>
                                                @elseif($value === false)
                                                    <span class="text-subtle" aria-hidden="true">–</span><span class="sr-only">Not included</span>
                                                @else
                                                    <span class="text-foreground">{{ $value }}</span>
                                                @endif
                                            </td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        @endforeach
                    </table>
                </div>
            </div>
        </section>
    @endif

    <x-landing.faq :items="$faq" description="Questions about plans, payments and what happens when a plan ends." />
    <x-marketing.related current="pricing" />
@endsection
