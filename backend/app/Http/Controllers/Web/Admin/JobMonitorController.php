<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Queries\Admin\AdminJobMonitorQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class JobMonitorController extends Controller
{
    public function __construct(
        protected AdminJobMonitorQuery $jobMonitorQuery
    ) {}

    /**
     * Render the admin processing queue & exports monitor page.
     * GET /admin/queue
     */
    public function index(Request $request): View
    {
        $data = $this->jobMonitorQuery->forIndex($request);

        return view('admin.jobs', $data);
    }

    /**
     * Lightweight status JSON endpoint for continuous smart-polling.
     * GET /admin/queue/status
     */
    public function status(Request $request): JsonResponse
    {
        $payload = $this->jobMonitorQuery->statusPayload($request);

        return response()->json($payload);
    }
}
