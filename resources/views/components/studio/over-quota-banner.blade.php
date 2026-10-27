@php
    $overQuotaUser = auth()->check() ? request()->account() : null;
    $overQuotaStage = $overQuotaUser?->overQuotaStage();
@endphp
@if($overQuotaStage)
    @php
        $dates = \App\Enums\OverQuotaStage::dates($overQuotaUser->over_quota_since);
        $limit = \Illuminate\Support\Number::fileSize((int) $overQuotaUser->plan->storage_limit, 1);
        $over = \Illuminate\Support\Number::fileSize((int) $overQuotaUser->storage_used_bytes - (int) $overQuotaUser->plan->storage_limit, 1);
        $message = match ($overQuotaStage) {
            \App\Enums\OverQuotaStage::Grace => "Uploads are paused. On {$dates['restrict']->format('M j')} new galleries and client downloads pause.",
            \App\Enums\OverQuotaStage::Restricted => "Uploads, new galleries and client downloads are paused. On {$dates['archive']->format('M j')} your galleries go offline for clients.",
            default => "Your galleries are offline for clients. On {$dates['delete']->format('M j')} your oldest galleries will be permanently deleted.",
        };
    @endphp
    <div role="alert" class="relative z-[55] flex flex-col items-start justify-between gap-2 bg-destructive px-4 py-2 text-white sm:flex-row sm:items-center">
        <p class="flex items-start gap-2 text-[13px]">
            <x-icon name="triangle-alert" class="mt-0.5 size-4 shrink-0" />
            <span><strong class="font-semibold">You're {{ $over }} over the Free plan's {{ $limit }}.</strong> {{ $message }}</span>
        </p>
        <div class="flex shrink-0 gap-2">
            <a href="{{ route('studio.galleries.index') }}" class="btn btn-xs bg-white/15 text-white hover:bg-white/25">Free up space</a>
            @can('studio.billing')
                <a href="{{ route('studio.billing.index') }}" class="btn btn-xs bg-white text-destructive hover:bg-white/90">Upgrade</a>
            @endcan
        </div>
    </div>
@endif
