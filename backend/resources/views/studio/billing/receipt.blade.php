@extends('layouts.app', ['title' => 'Payment Receipt - Studio'])

@push('styles')
<style>
@media print {
    /* Hide layout chrome and action buttons */
    aside,
    header,
    .no-print,
    [x-data*="toastNotification"] {
        display: none !important;
    }

    body,
    html,
    .flex.h-full,
    .flex.flex-1 {
        background: #ffffff !important;
        color: #111827 !important;
        height: auto !important;
        overflow: visible !important;
        display: block !important;
        padding: 0 !important;
        margin: 0 !important;
    }

    main {
        padding: 0 !important;
        margin: 0 !important;
        max-width: 100% !important;
    }

    #printable-receipt {
        border: 1px solid #e5e7eb !important;
        box-shadow: none !important;
        background: #ffffff !important;
        color: #111827 !important;
        max-width: 100% !important;
        width: 100% !important;
        border-radius: 8px !important;
        padding: 32px !important;
        margin: 0 auto !important;
    }

    #printable-receipt .receipt-logo {
        width: 32px !important;
        height: 32px !important;
        max-width: 32px !important;
        max-height: 32px !important;
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

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <!-- Top Action Bar (Hidden when printing) -->
    <div class="flex items-center justify-between pb-4 border-b border-border no-print">
        <a href="{{ route('studio.billing.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-muted-foreground hover:text-foreground transition-colors">
            &larr; Back to Billing
        </a>
        <div class="flex items-center gap-2">
            <button type="button"
                    onclick="window.print()"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-border bg-card text-xs font-semibold text-foreground hover:bg-secondary transition-all shadow-sm">
                <svg class="w-4 h-4 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                </svg>
                <span>Print</span>
            </button>
            <button type="button"
                    onclick="window.print()"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-primary text-primary-foreground text-xs font-semibold hover:bg-primary/90 transition-all shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                <span>Download PDF</span>
            </button>
        </div>
    </div>

    <!-- Clean Printable Receipt Document -->
    <div class="bg-card border border-border rounded-2xl p-6 sm:p-10 shadow-sm space-y-8 relative" id="printable-receipt">
        <!-- Receipt Header -->
        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-6 pb-6 border-b border-border">
            <!-- Brand -->
            <div class="space-y-2">
                <div class="flex items-center gap-2.5">
                    <img src="{{ asset('logo.png') }}"
                         alt="ifotoset"
                         class="receipt-logo w-8 h-8 object-contain shrink-0 drop-shadow-sm"
                         width="32"
                         height="32"
                         style="width: 32px; height: 32px; object-fit: contain;">
                    <span class="text-2xl font-bold tracking-tight text-foreground">
                        ifoto<span class="text-primary">set</span>
                    </span>
                </div>
                <div class="text-xs text-muted-foreground leading-relaxed">
                    <div>Kigali, Rwanda</div>
                    <div>support@ifotoset.com</div>
                </div>
            </div>

            <!-- Receipt Meta -->
            <div class="text-left sm:text-right space-y-2">
                <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                    Paid
                </div>
                <div>
                    <div class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Receipt</div>
                    <div class="font-mono font-bold text-foreground text-sm mt-0.5">#RCT-{{ strtoupper(substr($payment->uuid, 0, 10)) }}</div>
                    <div class="text-xs text-muted-foreground mt-0.5">
                        {{ $payment->paid_at ? $payment->paid_at->format('F j, Y') : $payment->created_at->format('F j, Y') }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Customer & Payment Details -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 text-xs">
            <div class="space-y-1">
                <div class="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider">Billed To</div>
                <div class="font-semibold text-foreground text-sm">{{ $user->name }}</div>
                <div class="text-muted-foreground">{{ $user->email }}</div>
            </div>

            <div class="space-y-1 sm:text-right">
                <div class="text-[11px] font-semibold text-muted-foreground uppercase tracking-wider">Payment Method</div>
                <div class="font-semibold text-foreground text-sm">
                    @php
                        $prov = strtolower($payment->provider ?? '');
                    @endphp
                    @if(str_contains($prov, 'mtn'))
                        MTN Mobile Money
                    @elseif(str_contains($prov, 'airtel'))
                        Airtel Money
                    @else
                        Mobile Money
                    @endif
                </div>
                @if($payment->phone_number)
                    <div class="font-mono text-muted-foreground">+{{ $payment->phone_number }}</div>
                @endif
            </div>
        </div>

        <!-- Line Items -->
        <div class="border border-border rounded-xl overflow-hidden">
            <table class="w-full text-left text-xs">
                <thead class="bg-secondary/40 text-muted-foreground font-semibold uppercase tracking-wider text-[11px] border-b border-border">
                    <tr>
                        <th class="px-5 py-3">Description</th>
                        <th class="px-5 py-3">Duration</th>
                        <th class="px-5 py-3 text-right">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr>
                        <td class="px-5 py-4">
                            <div class="font-semibold text-foreground text-sm">{{ $payment->plan->name ?? 'Subscription' }} Plan</div>
                        </td>
                        <td class="px-5 py-4 text-muted-foreground whitespace-nowrap">
                            @if(isset($payment->metadata['months']))
                                {{ $payment->metadata['months'] }} {{ \Illuminate\Support\Str::plural('Month', $payment->metadata['months']) }}
                            @elseif($payment->billing_cycle === 'annual')
                                1 Year
                            @else
                                1 Month
                            @endif
                        </td>
                        <td class="px-5 py-4 text-right font-mono font-semibold text-foreground text-sm whitespace-nowrap">
                            {{ number_format($payment->amount, 0) }} {{ $payment->currency }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Totals -->
        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-6 pt-2">
            <div class="text-xs text-muted-foreground max-w-xs leading-relaxed">
                Thank you for your business. For any questions regarding this receipt, contact <a href="mailto:support@ifotoset.com" class="text-foreground hover:underline">support@ifotoset.com</a>.
            </div>

            <div class="w-full sm:w-60 space-y-2 text-xs">
                <div class="flex justify-between text-muted-foreground">
                    <span>Subtotal</span>
                    <span class="font-mono font-medium text-foreground">{{ number_format($payment->amount, 0) }} {{ $payment->currency }}</span>
                </div>
                <div class="flex justify-between items-baseline pt-2 border-t border-border">
                    <span class="font-bold text-foreground text-sm">Total Paid</span>
                    <span class="font-mono text-lg font-bold text-foreground">{{ number_format($payment->amount, 0) }} {{ $payment->currency }}</span>
                </div>
            </div>
        </div>

        <!-- Footer Reference -->
        <div class="pt-4 border-t border-border flex flex-col sm:flex-row items-center justify-between gap-2 text-[11px] text-muted-foreground">
            <div>Transaction Reference: <span class="font-mono">{{ $payment->provider_transaction_id ?? $payment->uuid }}</span></div>
            <div>ifotoset.com</div>
        </div>
    </div>
</div>
@endsection
