<?php

namespace App\Http\Controllers\Web\Studio;

use App\Actions\Billing\FinalizePawaPayPayment;
use App\Actions\Billing\InitiatePawaPayPayment;
use App\Contracts\PaymentGateway;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\StorageStatisticsService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BillingController extends Controller
{
    public function __construct(
        protected InitiatePawaPayPayment $initiator,
        protected FinalizePawaPayPayment $finalizer,
        protected PaymentGateway $gateway
    ) {}

    /**
     * Display subscription plans and billing management.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $currentPlan = $user->plan;
        $plans = Plan::orderBy('monthly_price')->get();

        $activeSubscription = Subscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->latest('ends_at')
            ->first();

        // Check for recent pending payment (created in last 30 minutes)
        $pendingPayment = Payment::where('user_id', $user->id)
            ->where('status', 'pending')
            ->where('created_at', '>', now()->subMinutes(30))
            ->latest()
            ->first();

        $storageService = app(StorageStatisticsService::class);
        $storageStats = $storageService->getStorageStats($user);

        return view('studio.billing.index', [
            'user' => $user,
            'currentPlan' => $currentPlan,
            'plans' => $plans,
            'activeSubscription' => $activeSubscription,
            'pendingPayment' => $pendingPayment,
            'storage' => [
                'used_bytes' => $storageStats['used_bytes'],
                'limit_bytes' => $storageStats['limit_bytes'] ?? 2147483648,
                'percentage' => $storageStats['percent_used'],
            ],
        ]);
    }

    /**
     * Display checkout screen for a specific plan.
     */
    public function checkout(Request $request, Plan $plan): View|RedirectResponse
    {
        if ($plan->slug === 'free') {
            return redirect()->route('studio.billing.index')
                ->with('info', 'The Free tier is active by default with 2 GB storage.');
        }

        $user = $request->user();
        $billingCycle = $request->query('cycle', 'monthly');
        $billingCycle = in_array(strtolower($billingCycle), ['annual', 'yearly']) ? 'annual' : 'monthly';

        $months = (int) $request->query('months', $billingCycle === 'annual' ? 12 : 1);
        if ($months < 1) $months = 1;
        if ($months > 36) $months = 36;

        $monthlyPrice = (float) $plan->monthly_price;
        $annualPrice = (float) $plan->annual_price;

        if ($months === 12 && $annualPrice > 0) {
            $price = $annualPrice;
        } else {
            $price = $monthlyPrice * $months;
        }

        return view('studio.billing.checkout', [
            'user' => $user,
            'plan' => $plan,
            'billingCycle' => $billingCycle,
            'months' => $months,
            'monthlyPrice' => $monthlyPrice,
            'annualPrice' => $annualPrice,
            'price' => $price,
        ]);
    }

    /**
     * Initiate Mobile Money payment deposit.
     */
    public function initiate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'plan_slug' => ['required', 'string', 'exists:plans,slug'],
            'billing_cycle' => ['nullable', 'string', 'max:50'],
            'months' => ['nullable', 'integer', 'min:1', 'max:36'],
            'phone_number' => ['required', 'string', 'max:25'],
            'provider' => ['nullable', 'string', 'max:50'],
        ]);

        $user = $request->user();
        $plan = Plan::where('slug', $validated['plan_slug'])->firstOrFail();

        $months = (int) ($validated['months'] ?? 0);
        if ($months < 1) {
            $cycle = strtolower((string) ($validated['billing_cycle'] ?? 'monthly'));
            if ($cycle === 'annual' || $cycle === 'yearly') {
                $months = 12;
            } elseif (preg_match('/^(\d+)_months$/', $cycle, $matches)) {
                $months = (int) $matches[1];
            } else {
                $months = 1;
            }
        }
        if ($months < 1) $months = 1;
        if ($months > 36) $months = 36;

        $billingCycle = ($months === 12) ? 'annual' : (($months === 1) ? 'monthly' : "{$months}_months");

        try {
            $payment = $this->initiator->execute(
                user: $user,
                plan: $plan,
                billingCycle: $billingCycle,
                phoneNumber: $validated['phone_number'],
                provider: $validated['provider'] ?? null,
                months: $months
            );

            return response()->json([
                'success' => true,
                'payment_uuid' => $payment->uuid,
                'deposit_id' => $payment->pawapay_deposit_id,
                'status' => $payment->status,
                'phone' => $payment->phone_number,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'months' => $months,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Bounded status polling endpoint.
     * Queries PawaPay API directly as fallback if still marked pending.
     */
    public function check(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();
        $payment = Payment::where('uuid', $uuid)
            ->where('user_id', $user->id)
            ->first();

        if (!$payment) {
            return response()->json(['success' => false, 'message' => 'Payment not found'], 404);
        }

        // Active server-side status query against PawaPay V2 if locally pending
        if ($payment->status === 'pending' && $payment->pawapay_deposit_id) {
            try {
                $statusData = $this->gateway->verifyDepositStatus($payment->pawapay_deposit_id);

                if ($statusData->found && $statusData->isCompleted()) {
                    $payment = $this->finalizer->execute($payment->pawapay_deposit_id, $statusData);
                } elseif ($statusData->found && $statusData->isFailed()) {
                    $payment = $this->finalizer->execute($payment->pawapay_deposit_id, $statusData);
                }
            } catch (Exception $e) {
                // Keep local state on API network error
            }
        }

        return response()->json([
            'status' => $payment->status,
            'is_completed' => ($payment->status === 'completed'),
            'is_failed' => in_array($payment->status, ['failed', 'verification_failed']),
            'failure_reason' => $payment->failure_reason ?? $payment->error_message,
            'plan_name' => $payment->plan?->name,
            'paid_at' => $payment->paid_at?->toIso8601String(),
        ]);
    }

    /**
     * Display printable payment receipt.
     */
    public function receipt(Request $request, string $uuid): View
    {
        $user = $request->user();
        $payment = Payment::where('uuid', $uuid)
            ->where('user_id', $user->id)
            ->with(['plan', 'user'])
            ->firstOrFail();

        return view('studio.billing.receipt', [
            'payment' => $payment,
            'user' => $user,
        ]);
    }
}
