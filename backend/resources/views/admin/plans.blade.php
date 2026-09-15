@extends('layouts.admin', ['title' => 'Plans & Pricing - Admin'])

@section('content')
<div class="space-y-8">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-border">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-foreground">Payment Plans &amp; Storage Quotas</h1>
            <p class="text-xs text-muted-foreground mt-1">Configure subscription pricing, annual discount rates, and cloud storage allocations for all tiers.</p>
        </div>
        <div class="text-xs text-muted-foreground">
            Total Plans: <span class="font-bold text-foreground">{{ $plans->count() }}</span>
        </div>
    </div>

    <!-- Metrics Cards -->
    <div class="grid sm:grid-cols-3 gap-6">
        <x-studio.stat-card label="Paid Subscribers" :value="number_format($totalSubscribers)" />
        <x-studio.stat-card label="Est. Monthly Run-rate" :value="'RWF ' . number_format($estimatedMonthlyMrr, 0)" />
        <x-studio.stat-card label="Active Tiers" :value="$plans->count() . ' Configured'" />
    </div>

    <!-- Plans Table -->
    <div class="rounded-2xl border border-border bg-card overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-secondary/40 text-xs uppercase tracking-wider text-muted-foreground border-b border-border">
                    <tr>
                        <th class="px-6 py-4">Plan</th>
                        <th class="px-6 py-4">Monthly Price</th>
                        <th class="px-6 py-4">Annual Price</th>
                        <th class="px-6 py-4">Storage Allocation</th>
                        <th class="px-6 py-4">Gallery Cap</th>
                        <th class="px-6 py-4">Subscribers</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @foreach($plans as $plan)
                        @php
                            $storageGb = round($plan->storage_limit / 1000000000, 1);
                            if ($plan->slug === 'free') {
                                $storageGb = 2; // standard 2 GB
                            }
                        @endphp
                        <tr class="hover:bg-muted/30 transition-colors"
                            x-data="{
                                editModalOpen: false,
                                unlimGalleries: {{ is_null($plan->gallery_limit) ? 'true' : 'false' }}
                            }">
                            <td class="px-6 py-4">
                                <div class="font-bold text-foreground">{{ $plan->name }}</div>
                                <div class="text-xs text-muted-foreground font-mono">slug: {{ $plan->slug }}</div>
                            </td>
                            <td class="px-6 py-4 text-xs">
                                @if($plan->monthly_price > 0)
                                    <span class="font-bold text-primary">{{ $plan->currency }} {{ number_format($plan->monthly_price, 0) }}</span>
                                    <span class="text-muted-foreground">/ mo</span>
                                @else
                                    <span class="font-semibold text-green-600 dark:text-green-400">Free (0 RWF)</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-xs">
                                @if($plan->annual_price > 0)
                                    <span class="font-bold text-foreground">{{ $plan->currency }} {{ number_format($plan->annual_price, 0) }}</span>
                                    <span class="text-muted-foreground">/ yr</span>
                                @else
                                    <span class="text-muted-foreground">&ndash;</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-xs">
                                <span class="font-semibold text-foreground">
                                    @if($storageGb >= 1000)
                                        {{ round($storageGb / 1000, 1) }} TB ({{ number_format($storageGb) }} GB)
                                    @else
                                        {{ $storageGb }} GB
                                    @endif
                                </span>
                                <div class="text-[10px] text-muted-foreground font-mono">{{ number_format($plan->storage_limit) }} bytes</div>
                            </td>
                            <td class="px-6 py-4 text-xs">
                                @if(is_null($plan->gallery_limit))
                                    <x-ui.badge variant="success">Unlimited</x-ui.badge>
                                @else
                                    <span class="font-semibold text-foreground">{{ $plan->gallery_limit }} Galleries</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-xs font-semibold text-foreground">
                                {{ $plan->users_count }} users
                            </td>
                            <td class="px-6 py-4 text-right">
                                <button type="button"
                                        @click="editModalOpen = true"
                                        class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-secondary hover:bg-secondary/80 text-foreground transition-colors border border-border shadow-sm">
                                    Edit Pricing &amp; Quota
                                </button>

                                <!-- Edit Plan Modal -->
                                <div x-show="editModalOpen"
                                     style="display: none;"
                                     class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-foreground/30 backdrop-blur-sm">
                                    <div @click.away="editModalOpen = false" class="bg-card border border-border rounded-3xl p-6 sm:p-8 max-w-lg w-full shadow-2xl space-y-6 text-left">
                                        <div>
                                            <h3 class="text-lg font-bold text-foreground">Edit {{ $plan->name }} Plan</h3>
                                            <p class="text-xs text-muted-foreground mt-0.5">Adjust public pricing and storage quota. Changes apply immediately to new checkouts.</p>
                                        </div>

                                        <form method="POST" action="{{ route('admin.plans.update', $plan->slug) }}" class="space-y-4">
                                            @csrf
                                            @method('PUT')

                                            <div class="grid sm:grid-cols-2 gap-4">
                                                <div>
                                                    <label class="block text-xs font-semibold text-foreground mb-1">Display Name</label>
                                                    <input type="text" name="name" value="{{ $plan->name }}" required class="w-full rounded-xl border border-border bg-input px-3.5 py-2 text-xs font-medium text-foreground">
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-semibold text-foreground mb-1">Currency</label>
                                                    <input type="text" name="currency" value="{{ $plan->currency }}" required class="w-full rounded-xl border border-border bg-input px-3.5 py-2 text-xs font-mono font-medium text-foreground">
                                                </div>
                                            </div>

                                            <div class="grid sm:grid-cols-2 gap-4">
                                                <div>
                                                    <label class="block text-xs font-semibold text-foreground mb-1">Monthly Price ({{ $plan->currency }})</label>
                                                    <input type="number" step="0.01" min="0" name="monthly_price" value="{{ (float) $plan->monthly_price }}" required class="w-full rounded-xl border border-border bg-input px-3.5 py-2 text-xs font-mono font-medium text-foreground">
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-semibold text-foreground mb-1">Annual Price ({{ $plan->currency }})</label>
                                                    <input type="number" step="0.01" min="0" name="annual_price" value="{{ (float) $plan->annual_price }}" required class="w-full rounded-xl border border-border bg-input px-3.5 py-2 text-xs font-mono font-medium text-foreground">
                                                </div>
                                            </div>

                                            <div>
                                                <label class="block text-xs font-semibold text-foreground mb-1">Storage Quota in Gigabytes (GB)</label>
                                                <div class="flex items-center gap-2">
                                                    <input type="number" step="1" min="1" name="storage_gb" value="{{ (int) $storageGb }}" required class="w-full rounded-xl border border-border bg-input px-3.5 py-2 text-xs font-mono font-medium text-foreground">
                                                    <span class="text-xs font-mono font-semibold text-muted-foreground whitespace-nowrap">GB</span>
                                                </div>
                                                <p class="text-[11px] text-muted-foreground mt-1">e.g. 50 for 50GB, 1000 for 1TB, 3000 for 3TB.</p>
                                            </div>

                                            <div class="space-y-2 pt-2 border-t border-border">
                                                <label class="flex items-center gap-2 text-xs font-semibold text-foreground cursor-pointer">
                                                    <input type="checkbox" name="unlimited_galleries" value="1" x-model="unlimGalleries" class="rounded border-border text-primary focus:ring-primary w-4 h-4">
                                                    <span>Unlimited Galleries (Recommended)</span>
                                                </label>

                                                <div x-show="!unlimGalleries" class="pt-1">
                                                    <label class="block text-xs font-semibold text-foreground mb-1">Custom Gallery Limit Cap</label>
                                                    <input type="number" min="1" name="gallery_limit" value="{{ $plan->gallery_limit ?? 10 }}" class="w-full rounded-xl border border-border bg-input px-3.5 py-2 text-xs font-mono text-foreground">
                                                </div>
                                            </div>

                                            <div class="flex items-center justify-end gap-2 pt-4 border-t border-border">
                                                <button type="button" @click="editModalOpen = false" class="px-4 py-2 rounded-xl text-xs font-semibold text-muted-foreground hover:text-foreground">
                                                    Cancel
                                                </button>
                                                <button type="submit" class="px-5 py-2 rounded-xl text-xs font-bold bg-primary text-primary-foreground hover:bg-primary/90 transition-colors shadow-sm">
                                                    Save Changes
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
