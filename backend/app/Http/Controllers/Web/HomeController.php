<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Support\Seo\SeoMetadata;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class HomeController extends Controller
{
    /**
     * Display the application landing page.
     */
    public function index(): View
    {
        $plans = Cache::remember('landing_plans', 86400, function () {
            return Plan::orderBy('monthly_price', 'asc')->get();
        });

        return view('pages.home', [
            'plans' => $plans,
            'seo' => SeoMetadata::forHome(),
        ]);
    }
}
