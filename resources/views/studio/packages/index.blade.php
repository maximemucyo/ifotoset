@extends('layouts.app', ['title' => 'Packages · ifotoset'])

@section('content')
@php
    $collectsDeposits = auth()->user()->hasFeature('booking_deposits');
    $depositPlan = $collectsDeposits ? null : \App\Models\Plan::whereHas('features', fn ($q) => $q->where('code', 'booking_deposits'))->orderBy('monthly_price')->first();
    $depositUpgradeUrl = $depositPlan
        ? route('studio.billing.checkout', $depositPlan->slug) . '?return_to=' . urlencode(route('studio.packages.index'))
        : route('studio.billing.index') . '#plans';
    $hasUncollectedDeposits = !$collectsDeposits && $packages->getCollection()->contains(fn ($p) => $p->deposit_type !== 'none' && $p->deposit_amount > 0);
@endphp
<div x-data="{
    createModalOpen: false,
    editModalOpen: false,
    selectedPkg: null,
    openEdit(pkg) {
        this.selectedPkg = pkg;
        this.editModalOpen = true;
    }
}">
    <x-ui.page-header title="Packages" description="The sessions clients can book from your profile: pricing, duration, deposit and what's included.">
        @if($packages->total() > 0)
            <x-slot:meta>
                <div class="mt-3 flex flex-wrap items-center gap-2 text-[13px] text-muted-foreground">
                    <span class="inline-flex items-center gap-1.5"><x-icon name="package" class="size-3.5" /> <span class="font-medium tabular-nums text-foreground">{{ $packages->total() }}</span> {{ \Illuminate\Support\Str::plural('package', $packages->total()) }}</span>
                    @php $hiddenOnPage = $packages->getCollection()->where('is_active', false)->count(); @endphp
                    @if($hiddenOnPage > 0)
                        <span class="text-subtle">·</span>
                        <span>{{ $hiddenOnPage }} hidden from booking</span>
                    @endif
                </div>
            </x-slot:meta>
        @endif
        <x-slot:actions>
            <a href="{{ route('studio.availability.index') }}" class="btn btn-outline"><x-icon name="clock" /> Availability</a>
            <button type="button" @click="createModalOpen = true" class="btn btn-primary">
                <x-icon name="plus" /> New package
            </button>
        </x-slot:actions>
    </x-ui.page-header>

    @if($errors->any())
        <div class="alert alert-danger mb-6">
            <x-icon name="circle-alert" />
            <div>
                <p class="font-medium">Please check the package details.</p>
                <p class="mt-0.5 text-[13px]">{{ $errors->first() }}</p>
            </div>
        </div>
    @endif

    @if($hasUncollectedDeposits)
        <div class="alert alert-warning mb-6 flex-col items-start gap-3 sm:flex-row sm:items-center">
            <x-icon name="wallet" />
            <div class="flex-1">
                <p class="font-medium">Your clients aren't paying these deposits yet</p>
                <p class="mt-0.5 text-[13px]">On your current plan, clients book without paying. Upgrade to {{ $depositPlan?->name ?? 'a paid plan' }} and they pay the deposit by Mobile Money when they book, so the date is only held once it's paid.</p>
            </div>
            <a href="{{ $depositUpgradeUrl }}" class="btn btn-primary btn-sm shrink-0"><x-icon name="zap" /> Upgrade to collect deposits</a>
        </div>
    @endif

    @if($packages->isEmpty())
        <x-ui.empty-state icon="package"
            title="Create your first package"
            description="Packages are what clients book: a name, a price, how long it takes and what they get. Start with your most popular session and add more later."
            action-label="New package" action-click="createModalOpen = true" />
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach($packages as $pkg)
                @php
                    $deliverables = is_array($pkg->deliverables) ? array_values(array_filter($pkg->deliverables)) : [];
                    $hours = intdiv((int) $pkg->duration_minutes, 60);
                    $mins = (int) $pkg->duration_minutes % 60;
                    $durationLabel = $hours ? ($hours . 'h' . ($mins ? ' ' . $mins . 'm' : '')) : $mins . ' min';
                    $payload = [
                        'uuid' => $pkg->uuid,
                        'name' => $pkg->name,
                        'description' => $pkg->description,
                        'price' => $pkg->price,
                        'currency' => $pkg->currency,
                        'duration_minutes' => $pkg->duration_minutes,
                        'buffer_before_minutes' => $pkg->buffer_before_minutes,
                        'buffer_after_minutes' => $pkg->buffer_after_minutes,
                        'deliverables_text' => implode("\n", $pkg->deliverables ?? []),
                        'deposit_type' => $pkg->deposit_type,
                        'deposit_amount' => $pkg->deposit_amount,
                        'is_active' => $pkg->is_active,
                        'sort_order' => $pkg->sort_order,
                    ];
                @endphp
                <article class="card card-hover group relative flex flex-col {{ $pkg->is_active ? '' : 'bg-muted/30' }}">
                    <div class="flex items-start justify-between gap-3 p-5 pb-0">
                        <div class="min-w-0">
                            <h3 class="truncate text-[15px] font-semibold text-foreground {{ $pkg->is_active ? '' : 'text-muted-foreground' }}" title="{{ $pkg->name }}">{{ $pkg->name }}</h3>
                            <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                <span class="inline-flex items-center gap-1 text-xs text-muted-foreground"><x-icon name="timer" class="size-3.5" /> {{ $durationLabel }}</span>
                                @unless($pkg->is_active)
                                    <x-ui.badge variant="neutral">Hidden</x-ui.badge>
                                @endunless
                            </div>
                        </div>
                        <form method="POST" action="{{ route('studio.packages.toggle', $pkg->uuid) }}" class="shrink-0">
                            @csrf
                            <button type="submit" role="switch" aria-checked="{{ $pkg->is_active ? 'true' : 'false' }}"
                                    class="switch" title="{{ $pkg->is_active ? 'Hide from booking page' : 'Show on booking page' }}"
                                    aria-label="{{ $pkg->is_active ? 'Deactivate' : 'Activate' }} {{ $pkg->name }}">
                                <input type="checkbox" class="sr-only" tabindex="-1" disabled @checked($pkg->is_active)>
                                <span></span>
                            </button>
                        </form>
                    </div>

                    <div class="px-5 pt-4">
                        <div class="flex items-baseline gap-1.5">
                            <span class="font-display text-[32px] font-semibold leading-none tracking-tight tabular-nums text-foreground">{{ number_format($pkg->price, 0) }}</span>
                            <span class="text-sm font-medium text-muted-foreground">{{ $pkg->currency }}</span>
                        </div>
                        <p class="mt-2 text-xs text-muted-foreground">
                            @if($pkg->deposit_type === 'percentage' && $pkg->deposit_amount)
                                <span class="font-medium text-foreground">{{ rtrim(rtrim(number_format($pkg->deposit_amount, 2), '0'), '.') }}% deposit</span> @if($collectsDeposits) paid by Mobile Money at booking @else · <a href="{{ $depositUpgradeUrl }}" class="font-medium text-warning underline underline-offset-2">upgrade to collect it</a> @endif
                            @elseif($pkg->deposit_type === 'fixed' && $pkg->deposit_amount)
                                <span class="font-medium text-foreground">{{ $pkg->currency }} {{ number_format($pkg->deposit_amount, 0) }} deposit</span> @if($collectsDeposits) paid by Mobile Money at booking @else · <a href="{{ $depositUpgradeUrl }}" class="font-medium text-warning underline underline-offset-2">upgrade to collect it</a> @endif
                            @else
                                No deposit required
                            @endif
                        </p>
                        @if($pkg->description)
                            <p class="mt-3 line-clamp-2 text-[13px] leading-relaxed text-muted-foreground">{{ $pkg->description }}</p>
                        @endif
                    </div>

                    <div class="mx-5 mt-4 flex-1 border-t border-border pt-4">
                        @if(count($deliverables))
                            <ul class="space-y-2">
                                @foreach(array_slice($deliverables, 0, 5) as $item)
                                    <li class="flex items-start gap-2 text-[13px] text-foreground">
                                        <span class="mt-0.5 flex size-4 shrink-0 items-center justify-center rounded-full bg-success/10 text-success"><x-icon name="check" class="size-2.5" stroke-width="3" /></span>
                                        <span>{{ $item }}</span>
                                    </li>
                                @endforeach
                                @if(count($deliverables) > 5)
                                    <li class="pl-6 text-xs text-muted-foreground">+ {{ count($deliverables) - 5 }} more</li>
                                @endif
                            </ul>
                        @else
                            <button type="button" @click="openEdit({{ json_encode($payload) }})" class="flex w-full items-center gap-2 rounded-lg border border-dashed border-border px-3 py-2.5 text-left text-xs text-muted-foreground transition hover:border-border-strong hover:text-foreground">
                                <x-icon name="plus" class="size-3.5" /> Add what's included
                            </button>
                        @endif
                    </div>

                    <footer class="mt-5 flex items-center justify-between gap-2 border-t border-border px-5 py-3">
                        <span class="inline-flex items-center gap-1.5 text-xs text-muted-foreground">
                            <x-icon name="calendar-check" class="size-3.5" />
                            {{ $pkg->bookings_count }} {{ \Illuminate\Support\Str::plural('booking', $pkg->bookings_count) }}
                        </span>
                        <div class="flex items-center gap-1">
                            <button type="button" @click="openEdit({{ json_encode($payload) }})" class="btn btn-ghost btn-sm">
                                <x-icon name="pencil" /> Edit
                            </button>
                            <form method="POST" action="{{ route('studio.packages.destroy', $pkg->uuid) }}"
                                  @submit.prevent="$dispatch('confirm-action', { form: $el, title: @js('Archive ' . $pkg->name . '?'), message: 'It will be removed from your booking page. Existing bookings for this package are not affected.', confirm: 'Archive package' })">
                                @csrf
                                <button type="submit" class="btn btn-ghost btn-icon btn-sm text-muted-foreground hover:text-destructive" title="Archive package" aria-label="Archive {{ $pkg->name }}">
                                    <x-icon name="trash-2" />
                                </button>
                            </form>
                        </div>
                    </footer>
                </article>
            @endforeach

            <button type="button" @click="createModalOpen = true"
                    class="group flex min-h-64 flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-border-strong/70 bg-card/40 p-6 text-center transition hover:border-foreground/30 hover:bg-card">
                <span class="flex size-10 items-center justify-center rounded-lg border border-border bg-card text-muted-foreground shadow-xs transition group-hover:text-foreground">
                    <x-icon name="plus" class="size-4" />
                </span>
                <span>
                    <span class="block text-sm font-medium text-foreground">New package</span>
                    <span class="mt-0.5 block text-xs text-muted-foreground">Add another session type</span>
                </span>
            </button>
        </div>

        @if($packages->hasPages())
            <div class="mt-6">{{ $packages->links() }}</div>
        @endif
    @endif

    {{-- Create package modal --}}
    @component('studio.partials.business.modal', ['show' => 'createModalOpen', 'title' => 'New package', 'description' => 'Define a session clients can book.', 'icon' => 'package', 'maxWidth' => 'lg'])
        <form method="POST" action="{{ route('studio.packages.store') }}" x-data="{ depType: 'none' }">
            @csrf
            <div class="px-5 pt-4 pb-5 sm:px-6">
                @include('studio.partials.business.package-fields', ['prefix' => 'new-pkg', 'bind' => null])
            </div>
            @include('studio.partials.business.modal-footer', ['cancel' => 'createModalOpen = false', 'submit' => 'Create package'])
        </form>
    @endcomponent

    {{-- Edit package modal --}}
    @component('studio.partials.business.modal', ['show' => 'editModalOpen', 'title' => 'Edit package', 'description' => 'Changes apply to new bookings only.', 'icon' => 'pencil', 'maxWidth' => 'lg'])
        <form method="POST" :action="'/studio/packages/' + (selectedPkg?.uuid ?? '')"
              x-data="{ depType: 'none' }" x-effect="depType = selectedPkg?.deposit_type ?? 'none'">
            @csrf
            @method('PATCH')
            <div class="px-5 pt-4 pb-5 sm:px-6">
                @include('studio.partials.business.package-fields', ['prefix' => 'edit-pkg', 'bind' => 'selectedPkg'])
            </div>
            @include('studio.partials.business.modal-footer', ['cancel' => 'editModalOpen = false', 'submit' => 'Save changes'])
        </form>
    @endcomponent

    @include('studio.partials.business.confirm-dialog')
</div>
@endsection
