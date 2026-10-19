<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Queries\Admin\AdminDashboardQuery;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Admin platform overview dashboard.
     */
    public function index(AdminDashboardQuery $query): View
    {
        $metrics = $query->get();

        return view('admin.dashboard', $metrics);
    }
}
