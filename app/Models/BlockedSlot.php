<?php

namespace App\Models;

use App\Casts\UuidBinaryCast;
use App\Traits\HasBinaryUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlockedSlot extends Model
{
    use HasBinaryUuid;

    protected $fillable = [
        'uuid',
        'user_id',
        'starts_at',
        'ends_at',
        'reason',
        'source',
    ];

    protected $casts = [
        'uuid' => UuidBinaryCast::class,
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    /**
     * Scope to blocked slots overlapping a given range.
     */
    public function scopeOverlapping(Builder $query, $startsAt, $endsAt): Builder
    {
        return $query->where('starts_at', '<', $endsAt)
                     ->where('ends_at', '>', $startsAt);
    }

    protected static function booted()
    {
        // Auto-merge overlapping blocked ranges on save
        static::saving(function (BlockedSlot $blockedSlot) {
            if ($blockedSlot->starts_at >= $blockedSlot->ends_at) {
                throw new \InvalidArgumentException("Blocked slot starts_at must be before ends_at.");
            }

            // Find overlapping blocked slots (excluding itself if editing)
            $query = self::where('user_id', $blockedSlot->user_id)
                ->overlapping($blockedSlot->starts_at, $blockedSlot->ends_at);

            if ($blockedSlot->exists) {
                $query->where('id', '!=', $blockedSlot->id);
            }

            $overlaps = $query->get();

            if ($overlaps->isNotEmpty()) {
                $newStart = $blockedSlot->starts_at;
                $newEnd = $blockedSlot->ends_at;

                foreach ($overlaps as $overlap) {
                    if ($overlap->starts_at < $newStart) {
                        $newStart = $overlap->starts_at;
                    }
                    if ($overlap->ends_at > $newEnd) {
                        $newEnd = $overlap->ends_at;
                    }
                    // Delete the overlapping slots to merge them
                    $overlap->delete();
                }

                $blockedSlot->starts_at = $newStart;
                $blockedSlot->ends_at = $newEnd;
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
