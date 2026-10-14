<x-email.layout
    :title="$threshold >= 100 ? 'Storage Quota Reached - Action Required' : 'Storage Notice: 75% Quota Reached'"
    :preheader="$threshold >= 100
        ? 'Your ' . $limitFormatted . ' storage is full. New uploads are paused until you free up space or upgrade.'
        : 'You have used ' . $usedFormatted . ' of ' . $limitFormatted . ' (' . $percentUsed . '%). Upgrade to keep uploads running smoothly.'"
>
    @if($threshold >= 100)
        <x-email.heading
            eyebrow="Action required"
            title="Your storage is 100% full"
            subtitle="Your ifotoset account has reached capacity. New photo uploads are currently paused."
        />
    @else
        <x-email.heading
            eyebrow="Storage notice"
            title="Your studio is growing"
            subtitle="You have used 75% of your available storage quota."
        />
    @endif

    <x-email.text>Hi {{ $user->name }},</x-email.text>

    @if($threshold >= 100)
        <x-email.text>
            Your account has reached its <strong class="em-h" style="color: #111113; font-weight: 600;">{{ $limitFormatted }}</strong> storage quota. Existing galleries and photos remain safe and accessible to your clients, but new uploads are blocked until your quota is expanded or space is freed.
        </x-email.text>
    @else
        <x-email.text>
            You're currently storing <strong class="em-h" style="color: #111113; font-weight: 600;">{{ $usedFormatted }}</strong> of your <strong class="em-h" style="color: #111113; font-weight: 600;">{{ $limitFormatted }}</strong> quota ({{ $percentUsed }}%). To make sure upcoming shoots and client deliveries aren't interrupted, consider upgrading your plan.
        </x-email.text>
    @endif

    @php($barPercent = max(1, min(100, (int) $percentUsed)))
    <x-email.card title="Current storage usage">
        {{-- Usage meter --}}
        <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin: 2px 0 14px;">
            <tr>
                <td class="em-track" bgcolor="#e8e8e5" style="background-color: #e8e8e5; border-radius: 999px; height: 6px; line-height: 6px; font-size: 0;">
                    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="{{ $barPercent }}%">
                        <tr>
                            <td class="{{ $threshold >= 100 ? 'em-fill-accent' : 'em-fill' }}" bgcolor="{{ $threshold >= 100 ? '#f0532a' : '#111113' }}" style="background-color: {{ $threshold >= 100 ? '#f0532a' : '#111113' }}; border-radius: 999px; height: 6px; line-height: 6px; font-size: 0;">&nbsp;</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
        <x-email.detail-row label="Used space" :value="$usedFormatted" />
        <x-email.detail-row label="Plan quota" :value="$limitFormatted" />
        <x-email.detail-row label="Capacity" :value="$percentUsed . '%'" />
        <x-email.detail-row label="Current plan" :value="$user->plan?->name ?? 'Free Tier'" />
    </x-email.card>

    <x-email.button :url="$upgradeUrl" label="Upgrade storage plan" :showFallback="false" />

    @if($threshold >= 100)
        <x-email.callout>
            <strong class="em-h" style="color: #111113;">Prefer not to upgrade right now?</strong><br>
            <span class="em-muted" style="color: #6b6b72; font-size: 13px;">
                Free up space by permanently emptying deleted items in your
                <x-email.link :href="$trashUrl">Trash</x-email.link>
                or removing unused photos in your
                <x-email.link :href="$galleriesUrl">Galleries</x-email.link>.
            </span>
        </x-email.callout>
    @else
        <x-email.text muted size="13px" spacing="0">
            Upgrading to the <strong>Basic Plan</strong> unlocks 50 GB of storage (approx. 12,000 photos at 4MB each) with instant activation via MTN MoMo or Airtel Money.
        </x-email.text>
    @endif
</x-email.layout>
