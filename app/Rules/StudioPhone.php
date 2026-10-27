<?php

namespace App\Rules;

use App\Models\User;
use App\Support\Phone;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A valid phone number (read with the "phone_country" field) that is not already
 * used by the maximum number of studio accounts.
 */
class StudioPhone implements ValidationRule, DataAwareRule
{
    private array $data = [];

    public function __construct(private ?User $ignore = null)
    {
    }

    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $phone = Phone::normalize((string) $value, $this->data['phone_country'] ?? null);

        if (!$phone) {
            $fail('Enter a valid phone number.');

            return;
        }

        if (Phone::accountsUsing($phone, $this->ignore) >= Phone::MAX_ACCOUNTS) {
            $fail('This phone number is already linked to ' . Phone::MAX_ACCOUNTS . ' accounts. Please use a different number.');
        }
    }
}
