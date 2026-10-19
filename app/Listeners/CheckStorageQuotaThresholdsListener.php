<?php

namespace App\Listeners;

use App\Events\StorageRecalculatedEvent;
use App\Services\StorageQuotaNotifierService;

class CheckStorageQuotaThresholdsListener
{
    public function __construct(
        protected StorageQuotaNotifierService $notifierService
    ) {}

    public function handle(StorageRecalculatedEvent $event): void
    {
        $this->notifierService->evaluateUsage($event->userId);
    }
}
