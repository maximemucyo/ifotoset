@extends('layouts.admin', ['title' => 'Plans & pricing · Admin'])

@section('content')
@php
    $fmtMinutes = function (?int $seconds): string {
        $seconds = (int) $seconds;
        if ($seconds <= 0) return '—';
        $m = $seconds / 60;
        return $m >= 60 && fmod($m, 60) == 0 ? number_format($m / 60) . ' h' : number_format($m) . ' min';
    };
    $fmtGb = function (?int $bytes): string {
        $bytes = (int) $bytes;
        if ($bytes <= 0) return '—';
        $gb = $bytes / (1024 ** 3);
        return $gb >= 1 ? rtrim(rtrim(number_format($gb, 1), '0'), '.') . ' GB' : number_format($bytes / (1024 ** 2)) . ' MB';
    };
@endphp

<x-ui.page-header title="Plans & pricing" description="Subscription pricing, storage quotas and gallery limits for every tier. Changes apply to new checkouts immediately." />

{{-- KPI row --}}
<div class="grid gap-3 sm:grid-cols-3 sm:gap-4">
    <x-studio.stat-card label="Paid subscribers" :value="number_format($totalSubscribers)" icon="users" hint="On a paid tier" />
    <x-studio.stat-card label="Est. monthly run-rate" :value="'RWF ' . number_format($estimatedMonthlyMrr, 0)" icon="trending-up" hint="Monthly price × subscribers" />
    <x-studio.stat-card label="Tiers" :value="$plans->count()" icon="layers" hint="Configured plans" />
</div>

@if($plans->isEmpty())
    <x-ui.empty-state class="mt-6" icon="package" title="No plans configured" description="Seed the plans table to start selling subscriptions." />
@else
    <div class="mt-6 grid gap-4 xl:grid-cols-2">
        @foreach($plans as $plan)
            @php
                $storageGb = ($plan->storage_limit % 1000000000 === 0 && ($plan->storage_limit % (1024 * 1024) !== 0))
                    ? round($plan->storage_limit / 1000000000, 1)
                    : round($plan->storage_limit / (1024 * 1024 * 1024), 1);
                $isFree = (float) $plan->monthly_price <= 0;
                $annualSaving = (!$isFree && $plan->annual_price > 0 && $plan->monthly_price > 0)
                    ? (int) round((1 - ($plan->annual_price / ($plan->monthly_price * 12))) * 100)
                    : 0;
            @endphp
            <form method="POST" action="{{ route('admin.plans.update', $plan->slug) }}"
                  class="card flex flex-col"
                  x-data="{
                      unlimGalleries: {{ is_null($plan->gallery_limit) ? 'true' : 'false' }},
                      storage: {{ (int) $storageGb }},
                      dirty: false,
                      busy: false,
                  }"
                  @input="dirty = true" @change="dirty = true"
                  @submit="busy = true">
                @csrf
                @method('PUT')

                {{-- Header --}}
                <div class="flex items-start justify-between gap-4 border-b border-border px-5 py-4">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg {{ $isFree ? 'bg-muted text-muted-foreground' : 'bg-brand/10 text-brand' }}">
                            <x-icon :name="$isFree ? 'package' : ($plan->monthly_price >= 50000 ? 'crown' : 'zap')" class="size-4" />
                        </span>
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <h2 class="truncate text-[15px] font-semibold text-foreground">{{ $plan->name }}</h2>
                                <span class="rounded bg-muted px-1.5 py-0.5 font-mono text-[11px] text-muted-foreground">{{ $plan->slug }}</span>
                            </div>
                            <p class="mt-0.5 text-[13px] text-muted-foreground">
                                @if($isFree)
                                    Free tier
                                @else
                                    <span class="font-medium tabular-nums text-foreground">{{ $plan->currency }} {{ number_format($plan->monthly_price, 0) }}</span>/mo
                                    @if($annualSaving > 0)<span class="text-success">· {{ $annualSaving }}% off yearly</span>@endif
                                @endif
                            </p>
                        </div>
                    </div>
                    <div class="shrink-0 text-right">
                        <div class="font-display text-xl font-semibold tabular-nums text-foreground">{{ number_format($plan->users_count) }}</div>
                        <div class="text-xs text-muted-foreground">{{ \Illuminate\Support\Str::plural('subscriber', $plan->users_count) }}</div>
                    </div>
                </div>

                {{-- Editable fields --}}
                <div class="grid flex-1 gap-4 p-5 sm:grid-cols-2">
                    <div>
                        <label class="label" for="plan-{{ $plan->id }}-name">Display name</label>
                        <input id="plan-{{ $plan->id }}-name" type="text" name="name" value="{{ $plan->name }}" required class="input">
                    </div>
                    <div>
                        <label class="label" for="plan-{{ $plan->id }}-currency">Currency</label>
                        <input id="plan-{{ $plan->id }}-currency" type="text" name="currency" value="{{ $plan->currency }}" required maxlength="10" class="input font-mono uppercase">
                    </div>

                    <div>
                        <label class="label" for="plan-{{ $plan->id }}-monthly">Monthly price</label>
                        <div class="relative">
                            <input id="plan-{{ $plan->id }}-monthly" type="number" step="0.01" min="0" name="monthly_price" value="{{ (float) $plan->monthly_price }}" required class="input pr-14 tabular-nums">
                            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-muted-foreground">/ mo</span>
                        </div>
                    </div>
                    <div>
                        <label class="label" for="plan-{{ $plan->id }}-annual">Annual price</label>
                        <div class="relative">
                            <input id="plan-{{ $plan->id }}-annual" type="number" step="0.01" min="0" name="annual_price" value="{{ (float) $plan->annual_price }}" required class="input pr-14 tabular-nums">
                            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-muted-foreground">/ yr</span>
                        </div>
                    </div>

                    <div>
                        <label class="label" for="plan-{{ $plan->id }}-storage">Storage quota</label>
                        <div class="relative">
                            <input id="plan-{{ $plan->id }}-storage" type="number" step="1" min="1" name="storage_gb" value="{{ (int) $storageGb }}" x-model.number="storage" required class="input pr-12 tabular-nums">
                            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs font-medium text-muted-foreground">GB</span>
                        </div>
                        <p class="help" x-text="storage >= 1000 ? '≈ ' + (Math.round(storage / 100) / 10) + ' TB' : 'e.g. 50 for 50 GB, 1000 for 1 TB'">e.g. 50 for 50 GB, 1000 for 1 TB</p>
                    </div>

                    <div>
                        <span class="label">Galleries</span>
                        <div class="flex h-10 items-center gap-3">
                            <input type="hidden" name="unlimited_galleries" value="0">
                            <label class="switch" title="Unlimited galleries">
                                <input type="checkbox" name="unlimited_galleries" value="1" x-model="unlimGalleries" class="sr-only">
                                <span></span>
                            </label>
                            <span class="text-[13px] text-foreground" x-text="unlimGalleries ? 'Unlimited' : 'Capped at'">{{ is_null($plan->gallery_limit) ? 'Unlimited' : 'Capped at' }}</span>
                            <div x-show="!unlimGalleries" x-cloak class="relative w-28">
                                <input type="number" min="1" name="gallery_limit" value="{{ $plan->gallery_limit ?? 10 }}" class="input input-sm pr-3 tabular-nums" aria-label="Gallery limit">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="px-5 pb-5">
                    <label class="label" for="plan-{{ $plan->id }}-team">Team seats</label>
                    <div class="relative w-40">
                        <input id="plan-{{ $plan->id }}-team" type="number" step="1" min="0" max="100" name="team_limit" value="{{ (int) $plan->team_limit }}" required class="input pr-16 tabular-nums">
                        <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-muted-foreground">+ owner</span>
                    </div>
                    <p class="help">Only used when the plan has the <span class="font-mono">teams</span> feature. Lowering it pauses the newest members.</p>
                </div>

                {{-- Read-only video limits --}}
                <dl class="mx-5 mb-5 grid grid-cols-3 gap-px overflow-hidden rounded-lg border border-border bg-border">
                    @foreach([
                        ['Video quota', $fmtMinutes($plan->video_limit_seconds), 'video'],
                        ['Max clip length', $fmtMinutes($plan->max_single_video_duration_seconds), 'timer'],
                        ['Max video file', $fmtGb($plan->max_video_size_bytes), 'hard-drive'],
                    ] as [$label, $value, $icon])
                        <div class="bg-card px-3 py-2.5">
                            <dt class="flex items-center gap-1.5 text-[11px] text-muted-foreground"><x-icon :name="$icon" class="size-3" /> {{ $label }}</dt>
                            <dd class="mt-0.5 text-[13px] font-medium tabular-nums text-foreground">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>

                {{-- Footer --}}
                <div class="card-footer justify-between">
                    <p class="flex items-center gap-1.5 text-xs text-muted-foreground">
                        <span class="size-1.5 rounded-full transition-colors" :class="dirty ? 'bg-warning' : 'bg-border-strong'"></span>
                        <span x-text="dirty ? 'Unsaved changes' : 'Up to date'">Up to date</span>
                    </p>
                    <div class="flex items-center gap-2">
                        <button type="reset" class="btn btn-ghost btn-sm" x-show="dirty" x-cloak
                                @click="$nextTick(() => { dirty = false; unlimGalleries = {{ is_null($plan->gallery_limit) ? 'true' : 'false' }}; storage = {{ (int) $storageGb }}; })">Reset</button>
                        <button type="submit" class="btn btn-primary btn-sm" :disabled="busy">
                            <x-icon name="loader-circle" class="animate-spin" x-show="busy" x-cloak />
                            Save {{ $plan->name }}
                        </button>
                    </div>
                </div>
            </form>
        @endforeach
    </div>
    <p class="mt-4 flex items-center gap-1.5 text-xs text-muted-foreground">
        <x-icon name="info" class="size-3.5" /> Video limits are shown for reference and can’t be edited from this page.
    </p>
@endif
@endsection
