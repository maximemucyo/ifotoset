<x-email.layout title="Subscription Upgrade Confirmation" :preheader="'Payment received. Your account is now on the ' . $plan->name . ' and new limits are active.'">
    <x-email.heading
        eyebrow="Subscription"
        title="Subscription upgraded"
        :subtitle="'Welcome to the ifotoset ' . $plan->name . '! Your subscription payment was successful.'"
    />

    <x-email.text>Hi {{ $user->name }},</x-email.text>

    <x-email.text>
        Thank you for your payment. Your ifotoset account has been upgraded to the <strong class="em-h" style="color: #17110e; font-weight: 600;">{{ $plan->name }}</strong>. All upgraded features and expanded limits are active immediately.
    </x-email.text>

    <x-email.card title="Receipt & plan details">
        <x-email.detail-row label="Plan" :value="$plan->name" />
        @php
            $eLimit = $plan->storage_limit;
            $eGb = ($eLimit % 1000000000 === 0 && ($eLimit % (1024 * 1024) !== 0))
                ? round($eLimit / 1000000000, 1)
                : round($eLimit / (1024 * 1024 * 1024), 1);
        @endphp
        <x-email.detail-row label="Storage limit" :value="($eGb >= 1000 ? round($eGb / 1000, 1) . ' TB' : $eGb . ' GB')" />
        @if($plan->gallery_limit)
            <x-email.detail-row label="Galleries" :value="$plan->gallery_limit . ' Galleries'" />
        @else
            <x-email.detail-row label="Galleries" value="Unlimited" />
        @endif
        @php
            $cleanProvider = str_contains(strtolower($payment->provider ?? ''), 'airtel')
                ? 'Airtel Money'
                : (str_contains(strtolower($payment->provider ?? ''), 'mtn') ? 'MTN Mobile Money' : 'Mobile Money');
            $ref = $payment->provider_transaction_id ?? substr($payment->uuid, 0, 13);
        @endphp
        <x-email.detail-row label="Payment method" :value="$cleanProvider" />
        <x-email.detail-row label="Transaction ref" :value="$ref" />
        <x-email.detail-row label="Date" :value="now()->format('F j, Y \a\t g:i A')" />
    </x-email.card>

    <x-email.text muted spacing="0">
        Manage your galleries, upload high-resolution photos and customize your portfolio from your Studio dashboard.
    </x-email.text>

    <x-email.button :url="$dashboardUrl" label="Go to Studio" />
</x-email.layout>
