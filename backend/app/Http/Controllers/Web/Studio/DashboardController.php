<?php

namespace App\Http\Controllers\Web\Studio;

use App\Http\Controllers\Controller;
use App\Queries\Studio\StudioDashboardQuery;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Display the Studio photographer dashboard.
     */
    public function index(Request $request, StudioDashboardQuery $query): View
    {
        $data = $query->get($request->user());

        return view('studio.dashboard', $data);
    }
}
