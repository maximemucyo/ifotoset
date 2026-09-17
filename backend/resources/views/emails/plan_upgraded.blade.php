<x-email.layout title="Subscription Upgrade Confirmation">
    <x-email.heading 
        title="Subscription Upgraded!" 
        :subtitle="'Welcome to the ifotoset ' . $plan->name . '! Your subscription payment was successful.'" 
    />

    <p style="font-size: 15px; color: #44403c; line-height: 1.6; margin: 0 0 20px;">
        Hi {{ $user->name }},
    </p>

    <p style="font-size: 15px; color: #44403c; line-height: 1.6; margin: 0 0 20px;">
        Thank you for your payment. Your ifotoset account has been upgraded to the <strong>{{ $plan->name }}</strong>. All upgraded features and expanded limits are active on your account immediately.
    </p>

    <x-email.card title="Receipt & Plan Details">
        <x-email.detail-row label="Plan Tier" :value="$plan->name" />
        @php
            $eLimit = $plan->storage_limit;
            $eGb = ($eLimit % 1000000000 === 0 && ($eLimit % (1024 * 1024) !== 0))
                ? round($eLimit / 1000000000, 1)
                : round($eLimit / (1024 * 1024 * 1024), 1);
        @endphp
        <x-email.detail-row label="Storage Limit" :value="($eGb >= 1000 ? round($eGb / 1000, 1) . ' TB' : $eGb . ' GB')" />
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
        <x-email.detail-row label="Payment Method" :value="$cleanProvider" />
        <x-email.detail-row label="Transaction Ref" :value="$ref" />
        <x-email.detail-row label="Date" :value="now()->format('F j, Y \a\t g:i A')" />
    </x-email.card>

    <p style="font-size: 14px; color: #78716c; line-height: 1.6; margin: 0 0 24px;">
        You can manage your galleries, upload high-resolution photos, and customize your portfolio directly in your studio dashboard.
    </p>

    <x-email.button :url="$dashboardUrl" label="Go to Studio Dashboard" />
</x-email.layout>
