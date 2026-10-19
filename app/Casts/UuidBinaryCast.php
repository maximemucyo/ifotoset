<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Ramsey\Uuid\Uuid;

class UuidBinaryCast implements CastsAttributes
{
    /**
     * Cast the given value from database BINARY(16) to UUID string.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if (is_null($value)) {
            return null;
        }

        if (strlen($value) === 16) {
            return Uuid::fromBytes($value)->toString();
        }

        return (string) $value;
    }

    /**
     * Prepare the given value for storage into database BINARY(16).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if (is_null($value)) {
            return null;
        }

        if (is_string($value) && Uuid::isValid($value)) {
            return Uuid::fromString($value)->getBytes();
        }

        return $value;
    }
}
?>
