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
        <x-email.detail-row label="Amount Paid" :value="number_format((float)$payment->amount) . ' ' . $payment->currency" />
        <x-email.detail-row label="Storage Limit" :value="round($plan->storage_limit / (1024 * 1024 * 1024), 1) . ' GB'" />
        @if($plan->gallery_limit)
            <x-email.detail-row label="Galleries" :value="$plan->gallery_limit . ' Galleries'" />
        @else
            <x-email.detail-row label="Galleries" value="Unlimited" />
        @endif
        <x-email.detail-row label="Payment Provider" :value="$payment->provider" />
        <x-email.detail-row label="Transaction Ref" :value="$payment->pawapay_deposit_id ?? $payment->uuid" />
        <x-email.detail-row label="Date" :value="now()->format('F j, Y \a\t g:i A')" />
    </x-email.card>

    <p style="font-size: 14px; color: #78716c; line-height: 1.6; margin: 0 0 24px;">
        You can manage your galleries, upload high-resolution photos, and customize your portfolio directly in your studio dashboard.
    </p>

    <x-email.button :url="$dashboardUrl" label="Go to Studio Dashboard" />
</x-email.layout>
