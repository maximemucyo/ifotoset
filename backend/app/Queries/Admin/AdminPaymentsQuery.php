<?php

namespace App\Queries\Admin;

use App\Models\Payment;
use Illuminate\Pagination\LengthAwarePaginator;

class AdminPaymentsQuery
{
    /**
     * Fetch paginated payment transactions with optional status filter.
     */
    public function paginate(?string $status = null, int $perPage = 20): LengthAwarePaginator
    {
        $query = Payment::with(['user', 'booking.package', 'plan'])
            ->orderBy('created_at', 'desc');

        if (! empty($status) && in_array($status, ['completed', 'pending', 'failed'], true)) {
            $query->where('status', $status);
        }

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * Compute financial metrics from real payment records.
     */
    public function metrics(): array
    {
        $totalCompleted = Payment::where('status', 'completed')->sum('amount') ?: 0;
        $totalPending = Payment::where('status', 'pending')->sum('amount') ?: 0;
        $totalCount = Payment::count();
        $completedCount = Payment::where('status', 'completed')->count();

        $successRate = $totalCount > 0 ? round(($completedCount / $totalCount) * 100, 1) : 100.0;

        return [
            'totalCompleted' => $totalCompleted,
            'totalPending'   => $totalPending,
            'totalCount'     => $totalCount,
            'successRate'    => $successRate,
        ];
    }
}
