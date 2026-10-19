<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlanController extends Controller
{
    /**
     * Display subscription plans, current pricing, and subscriber counts.
     */
    public function index(): View
    {
        $plans = Plan::withCount(['users'])->orderBy('monthly_price')->get();

        $totalSubscribers = $plans->where('monthly_price', '>', 0)->sum('users_count');
        $estimatedMonthlyMrr = $plans->sum(fn ($p) => $p->monthly_price * $p->users_count);

        return view('admin.plans', [
            'plans' => $plans,
            'totalSubscribers' => $totalSubscribers,
            'estimatedMonthlyMrr' => $estimatedMonthlyMrr,
        ]);
    }

    /**
     * Update pricing and storage parameters for a specific plan.
     */
    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'monthly_price' => ['required', 'numeric', 'min:0'],
            'annual_price' => ['required', 'numeric', 'min:0'],
            'storage_gb' => ['required', 'numeric', 'min:1'],
            'unlimited_galleries' => ['nullable', 'boolean'],
            'gallery_limit' => ['nullable', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'max:10'],
        ]);

        $admin = $request->user();

        // Convert storage in GB to binary bytes (1 GB = 1024 * 1024 * 1024 bytes)
        $storageGb = (float) $validated['storage_gb'];
        $storageBytes = (int) round($storageGb * 1024 * 1024 * 1024);

        // Evaluate gallery limits (null = unlimited)
        $isUnlimitedGalleries = $request->boolean('unlimited_galleries', true);
        $galleryLimit = $isUnlimitedGalleries ? null : ($validated['gallery_limit'] ?? null);

        $oldData = [
            'name' => $plan->name,
            'monthly_price' => $plan->monthly_price,
            'annual_price' => $plan->annual_price,
            'storage_limit' => $plan->storage_limit,
            'gallery_limit' => $plan->gallery_limit,
            'currency' => $plan->currency,
        ];

        $plan->update([
            'name' => $validated['name'],
            'monthly_price' => $validated['monthly_price'],
            'annual_price' => $validated['annual_price'],
            'storage_limit' => $storageBytes,
            'gallery_limit' => $galleryLimit,
            'currency' => strtoupper($validated['currency']),
        ]);

        AdminAuditLog::record(
            $admin,
            'plan.pricing_updated',
            'Plan',
            (string) $plan->id,
            [
                'plan_slug' => $plan->slug,
                'old' => $oldData,
                'new' => [
                    'name' => $plan->name,
                    'monthly_price' => $plan->monthly_price,
                    'annual_price' => $plan->annual_price,
                    'storage_limit' => $plan->storage_limit,
                    'gallery_limit' => $plan->gallery_limit,
                    'currency' => $plan->currency,
                ],
            ]
        );

        return back()->with('success', "Plan '{$plan->name}' pricing and quotas updated successfully.");
    }
}
