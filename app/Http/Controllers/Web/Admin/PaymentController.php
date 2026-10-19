<?php

namespace App\Http\Controllers\Web\Admin;

use App\Actions\Billing\FinalizePawaPayPayment;
use App\Contracts\PaymentGateway;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Queries\Admin\AdminPaymentsQuery;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentController extends Controller
{
    /**
     * Display platform payment transactions.
     */
    public function index(Request $request, AdminPaymentsQuery $query): View
    {
        $status = $request->input('status');

        $payments = $query->paginate($status, 20);
        $metrics = $query->metrics();

        return view('admin.payments', array_merge([
            'payments' => $payments,
            'status'   => $status,
        ], $metrics));
    }

    /**
     * Admin manually queries PawaPay API to reconcile and synchronize status.
     */
    public function syncStatus(
        Request $request,
        int $id,
        PaymentGateway $gateway,
        FinalizePawaPayPayment $finalizer
    ): RedirectResponse {
        $payment = Payment::findOrFail($id);

        if (empty($payment->pawapay_deposit_id)) {
            return back()->with('error', 'Cannot sync transaction without PawaPay deposit ID.');
        }

        try {
            $statusData = $gateway->verifyDepositStatus($payment->pawapay_deposit_id);

            if (!$statusData->found) {
                return back()->with('warning', "Deposit not found on PawaPay API: {$statusData->failureReason}");
            }

            $updated = $finalizer->execute($payment->pawapay_deposit_id, $statusData);

            return back()->with('success', "Transaction synced with PawaPay. Status is now '{$updated->status}'.");
        } catch (Exception $e) {
            return back()->with('error', "Failed to sync transaction: {$e->getMessage()}");
        }
    }
}
