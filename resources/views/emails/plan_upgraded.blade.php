<x-email.layout :title="'You\'re on ' . $plan->name . ' now'" :preheader="'Payment received. Your studio is on the ' . $plan->name . ' plan and the new limits are active.'">
    <x-email.heading
        :title="'You\'re on ' . $plan->name . ' now'"
        subtitle="Your payment went through and the new limits are already active."
    />

    <x-email.text>Hi {{ $user->name }},</x-email.text>

    <x-email.text>
        Thanks for upgrading. Your studio is now on the <strong class="em-h" style="color: #1c201e; font-weight: 600;">{{ $plan->name }}</strong> plan.
    </x-email.text>

    <x-email.card title="Receipt">
        <x-email.detail-row label="Plan" :value="$plan->name" />
        <x-email.detail-row label="Storage" :value="\App\Support\Bytes::format($plan->storage_limit)" />
        <x-email.detail-row label="Galleries" :value="$plan->gallery_limit ? (string) $plan->gallery_limit : 'Unlimited'" />
        <x-email.detail-row label="Paid with" :value="$payment->providerLabel()" />
        <x-email.detail-row label="Reference" :value="$payment->provider_transaction_id ?? substr($payment->uuid, 0, 13)" />
        <x-email.detail-row label="Date" :value="now()->format('j F Y, g:i A')" />
    </x-email.card>

    <x-email.button :url="$dashboardUrl" label="Open your studio" :showFallback="false" />
</x-email.layout>
