@php($full = $threshold >= 100)
<x-email.layout
    :title="$full ? 'Your storage is full' : 'You\'ve used 75% of your storage'"
    :preheader="$full
        ? 'Your ' . $limitFormatted . ' of storage is full. New uploads are paused until you free up space or upgrade.'
        : 'You have used ' . $usedFormatted . ' of ' . $limitFormatted . '. Upgrade before it fills up so uploads keep working.'"
>
    <x-email.heading
        :title="$full ? 'Your storage is full' : 'You\'ve used ' . $percentUsed . '% of your storage'"
        :subtitle="$full
            ? 'New uploads are paused. Your galleries stay online and clients can still open them.'
            : 'Upgrade before it fills up so your next uploads aren\'t interrupted.'"
    />

    <x-email.text>Hi {{ $user->name }},</x-email.text>

    @php($barPercent = max(1, min(100, (int) $percentUsed)))
    <x-email.card>
        {{-- Usage meter --}}
        <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin: 4px 0 16px;">
            <tr>
                <td class="em-track" bgcolor="#e2e5df" style="background-color: #e2e5df; border-radius: 999px; height: 8px; line-height: 8px; font-size: 0;">
                    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="{{ $barPercent }}%">
                        <tr>
                            <td @class(['em-fill' => !$full]) bgcolor="{{ $full ? '#cc4019' : '#1c201e' }}" style="background-color: {{ $full ? '#cc4019' : '#1c201e' }}; border-radius: 999px; height: 8px; line-height: 8px; font-size: 0;">&nbsp;</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
        <x-email.detail-row label="Used" :value="$usedFormatted . ' of ' . $limitFormatted" />
        <x-email.detail-row label="Your plan" :value="$user->plan?->name ?? 'Free'" />
        @if($nextPlan)
            <x-email.detail-row :label="'On ' . $nextPlan->name" :value="\App\Support\Bytes::format($nextPlan->storage_limit) . ' of storage'" />
        @endif
    </x-email.card>

    <x-email.button :url="$upgradeUrl" :label="$nextPlan ? 'Upgrade to ' . $nextPlan->name : 'See plans'" :showFallback="false" />

    <x-email.callout>
        You can also free up space: empty your <x-email.link :href="$trashUrl">trash</x-email.link> or remove photos you no longer need from your <x-email.link :href="$galleriesUrl">galleries</x-email.link>.
    </x-email.callout>
</x-email.layout>
