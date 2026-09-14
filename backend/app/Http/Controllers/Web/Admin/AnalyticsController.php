<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Queries\Admin\AdminAnalyticsQuery;
use Illuminate\View\View;

class AnalyticsController extends Controller
{
    /**
     * Display platform analytics and growth metrics.
     */
    public function index(AdminAnalyticsQuery $query): View
    {
        $data = $query->get();

        return view('admin.analytics', $data);
    }
}
