<?php

namespace App\Actions\Studio;

use App\Models\BlockedSlot;
use App\Models\User;
use Carbon\Carbon;
use Ramsey\Uuid\Uuid;

class CreateBlockedSlot
{
    /**
     * Create a blackout/blocked slot range.
     */
    public function execute(User $user, array $data): BlockedSlot
    {
        $blocked = BlockedSlot::create([
            'uuid'      => Uuid::uuid7()->toString(),
            'user_id'   => $user->id,
            'starts_at' => new Carbon($data['starts_at']),
            'ends_at'   => new Carbon($data['ends_at']),
            'reason'    => $data['reason'] ?? null,
            'source'    => 'manual',
        ]);

        $user->clearAvailabilityCache();

        return $blocked;
    }
}
