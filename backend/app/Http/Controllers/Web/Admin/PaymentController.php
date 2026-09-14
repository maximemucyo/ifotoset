<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Queries\Admin\AdminPaymentsQuery;
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
}
