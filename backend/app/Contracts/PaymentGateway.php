<?php

namespace App\Contracts;

interface PaymentGateway
{
    /**
     * Initiates a payment deposit request.
     */
    public function initiateDeposit(array $data): array;

    /**
     * Queries deposit status from the provider API.
     */
    public function verifyDepositStatus(string $depositId): array;
}
?>
