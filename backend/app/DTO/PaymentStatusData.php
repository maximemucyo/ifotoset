<?php

namespace App\DTO;

class PaymentStatusData
{
    public function __construct(
        public bool $found,
        public string $status,
        public string $depositId,
        public float $amount,
        public string $currency,
        public ?string $provider = null,
        public ?string $providerTransactionId = null,
        public ?string $failureReason = null,
        public array $raw = []
    ) {}

    public function isCompleted(): bool
    {
        return strtoupper($this->status) === 'COMPLETED';
    }

    public function isFailed(): bool
    {
        return in_array(strtoupper($this->status), ['FAILED', 'EXPIRED', 'CANCELLED', 'REJECTED']);
    }

    public function isPending(): bool
    {
        return in_array(strtoupper($this->status), ['PENDING', 'ACCEPTED', 'PROCESSING', 'SUBMITTED']);
    }
}
