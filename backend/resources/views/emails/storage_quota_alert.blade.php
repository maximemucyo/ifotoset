<x-email.layout :title="$threshold >= 100 ? 'Storage Quota Reached - Action Required' : 'Storage Notice: 75% Quota Reached'">
    @if($threshold >= 100)
        <x-email.heading 
            title="Your Storage is 100% Full" 
            subtitle="Your ifotoset account has reached capacity. New photo uploads are currently paused." 
        />
    @else
        <x-email.heading 
            title="Your Studio is Growing!" 
            subtitle="You have used 75% of your available storage quota." 
        />
    @endif

    <p style="font-size: 15px; color: #44403c; line-height: 1.6; margin: 0 0 20px;">
        Hi {{ $user->name }},
    </p>

    @if($threshold >= 100)
        <p style="font-size: 15px; color: #44403c; line-height: 1.6; margin: 0 0 20px;">
            Your account has reached its <strong>{{ $limitFormatted }}</strong> storage quota. Existing galleries and photos remain completely safe and accessible to your clients, but new photo uploads will be blocked until your storage quota is expanded or space is freed.
        </p>
    @else
        <p style="font-size: 15px; color: #44403c; line-height: 1.6; margin: 0 0 20px;">
            You have currently stored <strong>{{ $usedFormatted }}</strong> of your <strong>{{ $limitFormatted }}</strong> quota ({{ $percentUsed }}%). To make sure your upcoming shoots and client deliveries are not interrupted, consider upgrading your plan today.
        </p>
    @endif

    <x-email.card title="Current Storage Usage">
        <x-email.detail-row label="Used Space" :value="$usedFormatted" />
        <x-email.detail-row label="Plan Quota" :value="$limitFormatted" />
        <x-email.detail-row label="Capacity" :value="$percentUsed . '%'" />
        <x-email.detail-row label="Current Plan" :value="$user->plan?->name ?? 'Free Tier'" />
    </x-email.card>

    <div style="margin: 24px 0 24px; text-align: center;">
        <x-email.button :url="$upgradeUrl" label="Upgrade Storage Plan" />
    </div>

    @if($threshold >= 100)
        <div style="background-color: #f5f5f4; border: 1px solid #e7e5e4; border-radius: 12px; padding: 16px; margin: 24px 0 16px;">
            <p style="margin: 0 0 8px; font-size: 13px; font-weight: 600; color: #292524;">
                Alternative: Free Up Space
            </p>
            <p style="margin: 0; font-size: 13px; color: #78716c; line-height: 1.5;">
                If you prefer not to upgrade right now, you can permanently empty deleted items in your 
                <a href="{{ $trashUrl }}" style="color: #2563eb; text-decoration: underline;">Trash</a> 
                or remove unused photos in your 
                <a href="{{ $galleriesUrl }}" style="color: #2563eb; text-decoration: underline;">Galleries</a>.
            </p>
        </div>
    @else
        <p style="font-size: 13px; color: #78716c; line-height: 1.5; margin: 16px 0 0; text-align: center;">
            Upgrading to the <strong>Basic Plan</strong> unlocks 50 GB of storage (approx. 12,000 photos at 4MB each) with instant activation via MTN MOMO or Airtel Money.
        </p>
    @endif
</x-email.layout>
