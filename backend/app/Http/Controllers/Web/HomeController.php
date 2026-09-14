<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\View\View;

class HomeController extends Controller
{
    /**
     * Display the application landing page.
     */
    public function index(): View
    {
        $plans = Plan::orderBy('monthly_price', 'asc')->get();

        return view('pages.home', [
            'plans' => $plans,
        ]);
    }
}
