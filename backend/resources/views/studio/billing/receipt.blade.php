@extends('layouts.app', ['title' => 'Receipt · ifotoset'])

@push('styles')
<style>
@media print {
    /* Always print in the light palette, whatever theme is active on screen */
    :root, .dark {
        color-scheme: light;
        /* Light tokens from resources/css/app.css (paper stays white) */
        --background: #ffffff;
        --foreground: #17110e;
        --card: #ffffff;
        --card-foreground: #17110e;
        --muted: #f7efe9;
        --muted-foreground: #6e6159;
        --subtle: #73645a;
        --brand: #f0532a;
        --brand-foreground: #ffffff;
        --brand-strong: #cc4019;
        --success: #16a34a;
        --warning: #d97706;
        --destructive: #e5383b;
        --border: #eee3db;
        --border-strong: #dfcfc4;
    }

    /* Hide app chrome and on-screen actions */
    aside,
    header,
    .no-print,
    [x-data="commandPalette"],
    [x-data*="toast"] {
        display: none !important;
    }

    html,
    body,
    body > div,
    body > div > div,
    body > div > div > div,
    #main,
    #main > div {
        background: #ffffff !important;
        height: auto !important;
        min-height: 0 !important;
        max-width: none !important;
        overflow: visible !important;
        display: block !important;
        padding: 0 !important;
        margin: 0 !important;
        border: 0 !important;
        border-radius: 0 !important;
        box-shadow: none !important;
    }

    #printable-receipt {
        max-width: 100% !important;
        margin: 0 !important;
        border: 1px solid #e8e8e5 !important;
        border-radius: 12px !important;
        box-shadow: none !important;
        break-inside: avoid;
    }

    * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    @page {
        size: A4 portrait;
        margin: 15mm;
    }
}
</style>
@endpush

@php
    $prov = strtolower($payment->provider ?? '');
    $method = str_contains($prov, 'mtn') ? 'MTN Mobile Money' : (str_contains($prov, 'airtel') ? 'Airtel Money' : 'Mobile Money');
    $months = $payment->metadata['months'] ?? null;
    if ($months) {
        $durationLabel = $months . ' ' . \Illuminate\Support\Str::plural('month', (int) $months);
    } elseif ($payment->billing_cycle === 'annual') {
        $durationLabel = '1 year';
    } else {
        $durationLabel = '1 month';
    }
    $issuedAt = $payment->paid_at ?? $payment->created_at;
    $receiptNo = 'RCT-' . strtoupper(substr($payment->uuid, 0, 10));
    $amount = number_format((float) $payment->amount, 0);
    $statusMap = [
        'completed' => ['success', 'Paid'],
        'pending' => ['warning', 'Pending'],
        'failed' => ['danger', 'Failed'],
        'verification_failed' => ['danger', 'Failed'],
    ];
    [$statusVariant, $statusLabel] = $statusMap[$payment->status] ?? ['neutral', ucfirst(str_replace('_', ' ', (string) $payment->status))];
    $isPaid = $payment->status === 'completed';
@endphp

@section('breadcrumbs')
    <a href="{{ route('studio.billing.index') }}" class="transition hover:text-foreground">Billing &amp; plans</a>
    <x-icon name="chevron-right" class="size-3.5 text-subtle" />
    <span class="font-medium text-foreground">Receipt {{ $receiptNo }}</span>
@endsection

@section('content')
<div class="mx-auto max-w-3xl">
    {{-- On-screen actions (hidden when printing) --}}
    <div class="no-print mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <a href="{{ route('studio.billing.index') }}" class="mb-3 inline-flex items-center gap-1.5 text-[13px] font-medium text-muted-foreground transition hover:text-foreground">
                <x-icon name="arrow-left" class="size-3.5" /> Back to billing
            </a>
            <h1 class="font-display text-2xl font-semibold tracking-tight text-foreground sm:text-[28px]">Receipt</h1>
            <p class="mt-1 text-sm text-muted-foreground">{{ $payment->plan->name ?? 'Subscription' }} plan · {{ $issuedAt->format('M j, Y') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" onclick="window.print()" class="btn btn-outline">
                <x-icon name="file-text" /> Print
            </button>
            <button type="button" onclick="window.print()" class="btn btn-primary">
                <x-icon name="download" /> Download PDF
            </button>
        </div>
    </div>

    {{-- Printable document --}}
    <article id="printable-receipt" class="card overflow-hidden">
        {{-- Header --}}
        <div class="flex flex-col gap-6 p-6 sm:flex-row sm:items-start sm:justify-between sm:p-10">
            <div>
                <x-brand.logo size="md" />
                <address class="mt-4 text-xs not-italic leading-relaxed text-muted-foreground">
                    ifotoset<br>
                    Kigali, Rwanda<br>
                    support@ifotoset.com
                </address>
            </div>
            <div class="sm:text-right">
                <p class="font-display text-2xl font-semibold tracking-tight text-foreground">Receipt</p>
                <p class="mt-1 text-sm tabular-nums text-muted-foreground">#{{ $receiptNo }}</p>
                <div class="mt-3"><x-ui.badge :variant="$statusVariant" dot>{{ $statusLabel }}</x-ui.badge></div>
            </div>
        </div>

        {{-- Amount hero --}}
        <div class="mx-6 flex flex-col gap-1 rounded-xl border border-border bg-muted/40 px-5 py-4 sm:mx-10 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs text-muted-foreground">{{ $isPaid ? 'Amount paid' : 'Amount' }}</p>
                <p class="font-display text-3xl font-semibold tracking-tight tabular-nums text-foreground">{{ $amount }} <span class="text-base font-medium text-muted-foreground">{{ $payment->currency }}</span></p>
            </div>
            <p class="text-sm text-muted-foreground">
                {{ $isPaid ? 'Paid on' : 'Created on' }} <span class="font-medium text-foreground">{{ $issuedAt->format('F j, Y') }}</span>
            </p>
        </div>

        {{-- Parties & meta --}}
        <dl class="grid grid-cols-1 gap-6 p-6 text-sm sm:grid-cols-3 sm:p-10">
            <div>
                <dt class="eyebrow !text-[11px]">Billed to</dt>
                <dd class="mt-2">
                    <span class="block font-medium text-foreground">{{ $user->name }}</span>
                    <span class="block text-muted-foreground">{{ $user->email }}</span>
                </dd>
            </div>
            <div>
                <dt class="eyebrow !text-[11px]">Payment method</dt>
                <dd class="mt-2">
                    <span class="block font-medium text-foreground">{{ $method }}</span>
                    @if($payment->phone_number)
                        <span class="block tabular-nums text-muted-foreground">+{{ $payment->phone_number }}</span>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="eyebrow !text-[11px]">Date</dt>
                <dd class="mt-2">
                    <span class="block font-medium tabular-nums text-foreground">{{ $issuedAt->format('M j, Y') }}</span>
                    <span class="block tabular-nums text-muted-foreground">{{ $issuedAt->format('H:i T') }}</span>
                </dd>
            </div>
        </dl>

        {{-- Line items --}}
        <div class="px-6 sm:px-10">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-border text-xs text-muted-foreground">
                        <th class="pb-3 font-medium">Description</th>
                        <th class="hidden pb-3 font-medium sm:table-cell">Period</th>
                        <th class="pb-3 text-right font-medium">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b border-border">
                        <td class="py-4 pr-4">
                            <span class="block font-medium text-foreground">{{ $payment->plan->name ?? 'Subscription' }} plan</span>
                            <span class="block text-xs text-muted-foreground sm:hidden">{{ ucfirst($durationLabel) }}</span>
                            <span class="hidden text-xs text-muted-foreground sm:block">ifotoset photographer subscription</span>
                        </td>
                        <td class="hidden whitespace-nowrap py-4 pr-4 text-muted-foreground sm:table-cell">{{ ucfirst($durationLabel) }}</td>
                        <td class="whitespace-nowrap py-4 text-right font-medium tabular-nums text-foreground">{{ $amount }} {{ $payment->currency }}</td>
                    </tr>
                </tbody>
            </table>

            <div class="ml-auto mt-4 w-full max-w-xs space-y-2 text-sm">
                <div class="flex justify-between text-muted-foreground">
                    <span>Subtotal</span>
                    <span class="tabular-nums text-foreground">{{ $amount }} {{ $payment->currency }}</span>
                </div>
                <div class="flex items-baseline justify-between border-t border-border pt-3">
                    <span class="font-medium text-foreground">{{ $isPaid ? 'Total paid' : 'Total' }}</span>
                    <span class="font-display text-lg font-semibold tabular-nums text-foreground">{{ $amount }} {{ $payment->currency }}</span>
                </div>
            </div>
        </div>

        {{-- Footer --}}
        <div class="mt-10 flex flex-col gap-2 border-t border-border bg-muted/40 px-6 py-5 text-xs text-muted-foreground sm:flex-row sm:items-center sm:justify-between sm:px-10">
            <p>Reference <span class="break-all font-mono text-foreground">{{ $payment->provider_transaction_id ?? $payment->uuid }}</span></p>
            <p>Questions? <a href="mailto:support@ifotoset.com" class="link">support@ifotoset.com</a></p>
        </div>
    </article>

    <p class="no-print mt-4 text-center text-xs text-muted-foreground">Tip: choose “Save as PDF” in the print dialog to download a copy.</p>
</div>
@endsection
