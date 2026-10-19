<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BlockedSlot;
use App\Models\Package;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ConflictDetector
{
    /**
     * Filters out candidate slots that overlap with confirmed bookings or blocked slots, taking buffers into account.
     */
    public function filterConflicts(
        User $photographer,
        Carbon $date,
        Package $package,
        Collection $candidates
    ): Collection {
        $timezone = $photographer->timezone ?: 'Africa/Kigali';
        
        // Define day boundaries in UTC for query optimization
        $startOfDayUtc = (clone $date)->startOfDay()->setTimezone('UTC');
        $endOfDayUtc = (clone $date)->endOfDay()->setTimezone('UTC');

        // 1. Fetch confirmed/blocking bookings overlapping this day
        $bookings = Booking::where('user_id', $photographer->id)
            ->whereIn('status', Booking::blockingStatuses())
            ->where('starts_at', '<=', $endOfDayUtc->addDay()) // Buffer margin
            ->where('ends_at', '>=', $startOfDayUtc->subDay())
            ->with('package')
            ->get();

        // 2. Fetch blocked slots overlapping this day
        $blockedSlots = BlockedSlot::where('user_id', $photographer->id)
            ->where('starts_at', '<=', $endOfDayUtc)
            ->where('ends_at', '>=', $startOfDayUtc)
            ->get();

        // 3. Pre-compute package buffer values for the candidate
        $candidateBufferBefore = (int) ($package->buffer_before_minutes ?? 0);
        $candidateBufferAfter = (int) ($package->buffer_after_minutes ?? 0);

        return $candidates->filter(function ($candidate) use ($bookings, $blockedSlots, $candidateBufferBefore, $candidateBufferAfter) {
            $candStart = $candidate['starts_at'];
            $candEnd = $candidate['ends_at'];

            // Compute occupied boundaries of candidate (including its own buffers)
            $candOccupiedStart = (clone $candStart)->subMinutes($candidateBufferBefore);
            $candOccupiedEnd = (clone $candEnd)->addMinutes($candidateBufferAfter);

            // Check overlap with each booking
            foreach ($bookings as $booking) {
                // Determine existing booking buffers
                $bookingBufferBefore = (int) ($booking->package?->buffer_before_minutes ?? 0);
                $bookingBufferAfter = (int) ($booking->package?->buffer_after_minutes ?? 0);

                // Compute occupied boundaries of existing booking
                $bookOccupiedStart = $booking->starts_at->subMinutes($bookingBufferBefore);
                $bookOccupiedEnd = $booking->ends_at->addMinutes($bookingBufferAfter);

                // Check overlap condition: starts < ends && ends > starts
                if ($candOccupiedStart->lessThan($bookOccupiedEnd) && $candOccupiedEnd->greaterThan($bookOccupiedStart)) {
                    return false; // Conflict found
                }
            }

            // Check overlap with manual blocked slots (which do not have package buffers)
            foreach ($blockedSlots as $blocked) {
                $blockStart = $blocked->starts_at;
                $blockEnd = $blocked->ends_at;

                if ($candOccupiedStart->lessThan($blockEnd) && $candOccupiedEnd->greaterThan($blockStart)) {
                    return false; // Conflict found
                }
            }

            return true; // No conflict
        })->values();
    }
}
