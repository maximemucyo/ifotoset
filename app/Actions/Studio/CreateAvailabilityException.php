<?php

namespace App\Actions\Studio;

use App\Models\AvailabilityException;
use App\Models\User;
use Ramsey\Uuid\Uuid;

class CreateAvailabilityException
{
    /**
     * Add or update a specific date exception.
     */
    public function execute(User $user, array $data): AvailabilityException
    {
        $isClosed = ! empty($data['is_closed']);

        $exception = AvailabilityException::updateOrCreate(
            [
                'user_id' => $user->id,
                'date'    => $data['date'],
            ],
            [
                'uuid'       => Uuid::uuid7()->toString(),
                'start_time' => $isClosed ? null : ($data['start_time'] . ':00'),
                'end_time'   => $isClosed ? null : ($data['end_time'] . ':00'),
                'is_closed'  => $isClosed,
            ]
        );

        $user->clearAvailabilityCache();

        return $exception;
    }
}
