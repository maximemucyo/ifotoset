@extends('layouts.app', ['title' => 'Pricing Packages - Studio'])

@section('content')
<div class="space-y-6" x-data="{
    createModalOpen: false,
    editModalOpen: false,
    selectedPkg: null,
    openEdit(pkg) {
        this.selectedPkg = pkg;
        this.editModalOpen = true;
    }
}">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-border">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-foreground">Photography Packages</h1>
            <p class="text-xs text-muted-foreground mt-1">Configure packages and session options available for client online booking.</p>
        </div>
        <button type="button"
                @click="createModalOpen = true"
                class="inline-flex items-center gap-2 px-4 py-2 text-xs font-semibold rounded-xl bg-primary text-primary-foreground shadow-sm hover:opacity-90 transition-opacity">
            + New Package
        </button>
    </div>

    <!-- Packages Grid -->
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($packages as $pkg)
            <div class="rounded-2xl border border-border bg-card p-6 shadow-sm flex flex-col justify-between hover:shadow-md transition-shadow relative">
                <div>
                    <!-- Header -->
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <h3 class="font-bold text-base text-foreground">{{ $pkg->name }}</h3>
                            <div class="text-xs text-muted-foreground mt-0.5">{{ $pkg->duration_minutes }} minutes session</div>
                        </div>
                        <form method="POST" action="{{ route('studio.packages.toggle', $pkg->uuid) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" title="Click to toggle status" class="cursor-pointer">
                                <x-ui.badge :variant="$pkg->is_active ? 'success' : 'muted'">
                                    {{ $pkg->is_active ? 'Active' : 'Inactive' }}
                                </x-ui.badge>
                            </button>
                        </form>
                    </div>

                    <!-- Price -->
                    <div class="mt-4 pt-3 border-t border-border flex items-baseline gap-1">
                        <span class="text-xs font-semibold text-primary uppercase">{{ $pkg->currency }}</span>
                        <span class="text-2xl font-bold text-foreground">{{ number_format($pkg->price, 0) }}</span>
                    </div>

                    @if($pkg->description)
                        <p class="text-xs text-muted-foreground mt-2 leading-relaxed">{{ $pkg->description }}</p>
                    @endif

                    <!-- Deposit Policy -->
                    <div class="mt-3 text-[11px] text-muted-foreground">
                        @if($pkg->deposit_type === 'percentage')
                            <span class="font-medium text-foreground">&bull; Deposit: {{ $pkg->deposit_amount }}% upon booking</span>
                        @elseif($pkg->deposit_type === 'fixed')
                            <span class="font-medium text-foreground">&bull; Deposit: {{ $pkg->currency }} {{ number_format($pkg->deposit_amount, 0) }}</span>
                        @else
                            <span>&bull; No upfront deposit required</span>
                        @endif
                    </div>

                    <!-- Deliverables List -->
                    @if(!empty($pkg->deliverables) && is_array($pkg->deliverables))
                        <div class="mt-4 space-y-1.5 pt-3 border-t border-border/60">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">Deliverables:</span>
                            @foreach($pkg->deliverables as $item)
                                <div class="flex items-center gap-2 text-xs text-foreground">
                                    <span class="text-primary font-bold">&check;</span>
                                    <span>{{ $item }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <!-- Footer Actions -->
                <div class="mt-6 pt-4 border-t border-border flex items-center justify-between text-xs">
                    <span class="text-muted-foreground">{{ $pkg->bookings_count }} sessions booked</span>

                    <div class="flex items-center gap-3">
                        <button type="button"
                                @click="openEdit({{ json_encode([
                                    'uuid' => $pkg->uuid,
                                    'name' => $pkg->name,
                                    'description' => $pkg->description,
                                    'price' => $pkg->price,
                                    'currency' => $pkg->currency,
                                    'duration_minutes' => $pkg->duration_minutes,
                                    'deliverables_text' => implode("\n", $pkg->deliverables ?? []),
                                    'deposit_type' => $pkg->deposit_type,
                                    'deposit_amount' => $pkg->deposit_amount,
                                    'is_active' => $pkg->is_active,
                                    'sort_order' => $pkg->sort_order,
                                ]) }})"
                                class="font-semibold text-primary hover:underline">
                            Edit
                        </button>

                        <form method="POST" action="{{ route('studio.packages.destroy', $pkg->uuid) }}" onsubmit="return confirm('Archive package {{ addslashes($pkg->name) }}?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-muted-foreground hover:text-destructive transition-colors">
                                Archive
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-3 text-center py-16 rounded-2xl border border-dashed border-border p-8">
                <p class="text-sm font-medium text-muted-foreground mb-4">No photography packages created yet.</p>
                <button type="button" @click="createModalOpen = true" class="px-4 py-2 bg-primary text-primary-foreground text-xs font-semibold rounded-xl shadow-sm hover:opacity-90">
                    Create Your First Package
                </button>
            </div>
        @endforelse
    </div>

    <div class="pt-4">
        {{ $packages->links() }}
    </div>

    <!-- Create Package Modal -->
    <div x-show="createModalOpen"
         x-transition
         class="fixed inset-0 z-50 overflow-y-auto"
         style="display: none;">
        <div class="fixed inset-0 bg-foreground/40 backdrop-blur-sm" @click="createModalOpen = false"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative w-full max-w-lg rounded-2xl bg-card border border-border p-6 shadow-2xl text-left"
                 x-data="{ depType: 'none' }">
                <div class="flex items-center justify-between pb-3 mb-4 border-b border-border">
                    <h3 class="text-base font-bold text-foreground">Create Photography Package</h3>
                    <button type="button" @click="createModalOpen = false" class="text-muted-foreground hover:text-foreground text-lg">&times;</button>
                </div>

                <form method="POST" action="{{ route('studio.packages.store') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-foreground mb-1">Package Title *</label>
                        <input type="text" name="name" required placeholder="e.g. Editorial Portrait Session" class="w-full rounded-lg border border-border bg-input px-3 py-2 text-xs text-foreground focus:border-primary focus:outline-none">
                    </div>

                    <div class="grid sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-foreground mb-1">Price *</label>
                            <input type="number" step="0.01" name="price" required placeholder="75000" class="w-full rounded-lg border border-border bg-input px-3 py-2 text-xs text-foreground focus:border-primary focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-foreground mb-1">Currency</label>
                            <input type="text" name="currency" value="RWF" required class="w-full rounded-lg border border-border bg-input px-3 py-2 text-xs text-foreground focus:border-primary focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-foreground mb-1">Duration (Mins)</label>
                            <input type="number" name="duration_minutes" value="60" required class="w-full rounded-lg border border-border bg-input px-3 py-2 text-xs text-foreground focus:border-primary focus:outline-none">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-foreground mb-1">Description (Optional)</label>
                        <textarea name="description" rows="2" placeholder="Summary of what this session entails..." class="w-full rounded-lg border border-border bg-input px-3 py-2 text-xs text-foreground focus:border-primary focus:outline-none"></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-foreground mb-1">Deliverables (One per line)</label>
                        <textarea name="deliverables_text" rows="3" placeholder="25 High-Resolution Edited Photos&#10;Private Online Gallery&#10;Full Commercial Usage Rights" class="w-full rounded-lg border border-border bg-input px-3 py-2 text-xs text-foreground font-mono focus:border-primary focus:outline-none"></textarea>
                    </div>

                    <div class="grid sm:grid-cols-2 gap-3 pt-1">
                        <div>
                            <label class="block text-xs font-medium text-foreground mb-1">Deposit Requirement</label>
                            <select name="deposit_type" x-model="depType" class="w-full rounded-lg border border-border bg-input px-3 py-2 text-xs text-foreground focus:border-primary focus:outline-none">
                                <option value="none">No Deposit Required</option>
                                <option value="percentage">Percentage (%)</option>
                                <option value="fixed">Fixed Amount</option>
                            </select>
                        </div>
                        <div x-show="depType !== 'none'">
                            <label class="block text-xs font-medium text-foreground mb-1" x-text="depType === 'percentage' ? 'Deposit %' : 'Deposit Amount'"></label>
                            <input type="number" step="0.01" name="deposit_amount" placeholder="e.g. 30" class="w-full rounded-lg border border-border bg-input px-3 py-2 text-xs text-foreground focus:border-primary focus:outline-none">
                        </div>
                    </div>

                    <label class="flex items-center gap-2 cursor-pointer pt-2">
                        <input type="checkbox" name="is_active" value="1" checked class="rounded border-border text-primary focus:ring-primary w-4 h-4">
                        <span class="text-xs font-medium text-foreground">Make this package active for public booking</span>
                    </label>

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-border">
                        <button type="button" @click="createModalOpen = false" class="px-4 py-2 text-xs font-semibold text-muted-foreground hover:text-foreground">Cancel</button>
                        <button type="submit" class="px-4 py-2 text-xs font-semibold rounded-xl bg-primary text-primary-foreground shadow-sm hover:opacity-90">Create Package</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Package Modal -->
    <div x-show="editModalOpen"
         x-transition
         class="fixed inset-0 z-50 overflow-y-auto"
         style="display: none;">
        <div class="fixed inset-0 bg-foreground/40 backdrop-blur-sm" @click="editModalOpen = false"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative w-full max-w-lg rounded-2xl bg-card border border-border p-6 shadow-2xl text-left"
                 x-data="{ depType: 'none' }">
                <div class="flex items-center justify-between pb-3 mb-4 border-b border-border">
                    <h3 class="text-base font-bold text-foreground">Edit Package</h3>
                    <button type="button" @click="editModalOpen = false" class="text-muted-foreground hover:text-foreground text-lg">&times;</button>
                </div>

                <form method="POST" :action="'/studio/packages/' + (selectedPkg?.uuid ?? '')" class="space-y-4">
                    @csrf
                    @method('PATCH')

                    <div>
                        <label class="block text-xs font-medium text-foreground mb-1">Package Title *</label>
                        <input type="text" name="name" required :value="selectedPkg?.name" class="w-full rounded-lg border border-border bg-input px-3 py-2 text-xs text-foreground focus:border-primary focus:outline-none">
                    </div>

                    <div class="grid sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-foreground mb-1">Price *</label>
                            <input type="number" step="0.01" name="price" required :value="selectedPkg?.price" class="w-full rounded-lg border border-border bg-input px-3 py-2 text-xs text-foreground focus:border-primary focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-foreground mb-1">Currency</label>
                            <input type="text" name="currency" required :value="selectedPkg?.currency" class="w-full rounded-lg border border-border bg-input px-3 py-2 text-xs text-foreground focus:border-primary focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-foreground mb-1">Duration (Mins)</label>
                            <input type="number" name="duration_minutes" required :value="selectedPkg?.duration_minutes" class="w-full rounded-lg border border-border bg-input px-3 py-2 text-xs text-foreground focus:border-primary focus:outline-none">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-foreground mb-1">Description (Optional)</label>
                        <textarea name="description" rows="2" x-text="selectedPkg?.description" class="w-full rounded-lg border border-border bg-input px-3 py-2 text-xs text-foreground focus:border-primary focus:outline-none"></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-foreground mb-1">Deliverables (One per line)</label>
                        <textarea name="deliverables_text" rows="3" x-text="selectedPkg?.deliverables_text" class="w-full rounded-lg border border-border bg-input px-3 py-2 text-xs text-foreground font-mono focus:border-primary focus:outline-none"></textarea>
                    </div>

                    <div class="grid sm:grid-cols-2 gap-3 pt-1">
                        <div>
                            <label class="block text-xs font-medium text-foreground mb-1">Deposit Requirement</label>
                            <select name="deposit_type" :value="selectedPkg?.deposit_type" class="w-full rounded-lg border border-border bg-input px-3 py-2 text-xs text-foreground focus:border-primary focus:outline-none">
                                <option value="none">No Deposit Required</option>
                                <option value="percentage">Percentage (%)</option>
                                <option value="fixed">Fixed Amount</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-foreground mb-1">Deposit Amount / %</label>
                            <input type="number" step="0.01" name="deposit_amount" :value="selectedPkg?.deposit_amount" class="w-full rounded-lg border border-border bg-input px-3 py-2 text-xs text-foreground focus:border-primary focus:outline-none">
                        </div>
                    </div>

                    <label class="flex items-center gap-2 cursor-pointer pt-2">
                        <input type="checkbox" name="is_active" value="1" :checked="selectedPkg?.is_active" class="rounded border-border text-primary focus:ring-primary w-4 h-4">
                        <span class="text-xs font-medium text-foreground">Make this package active for public booking</span>
                    </label>

                    <div class="flex items-center justify-end gap-3 pt-4 border-t border-border">
                        <button type="button" @click="editModalOpen = false" class="px-4 py-2 text-xs font-semibold text-muted-foreground hover:text-foreground">Cancel</button>
                        <button type="submit" class="px-4 py-2 text-xs font-semibold rounded-xl bg-primary text-primary-foreground shadow-sm hover:opacity-90">Update Package</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
