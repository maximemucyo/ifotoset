<?php

namespace App\Http\Controllers\Web\Studio;

use App\Http\Controllers\Controller;
use App\Queries\Studio\StudioAnalyticsQuery;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnalyticsController extends Controller
{
    /**
     * Display the Studio analytics dashboard.
     */
    public function index(Request $request, StudioAnalyticsQuery $query): View
    {
        $user = $request->user();
        $period = $request->input('period', '30d');

        $analytics = $query->get($user, $period);

        return view('studio.analytics.index', array_merge($analytics, ['user' => $user]));
    }
}
