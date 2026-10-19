<?php

namespace App\Traits;

use App\Builders\BinaryUuidBuilder;
use Ramsey\Uuid\Uuid;

trait HasBinaryUuid
{
    /**
     * Boot the trait to generate UUID on creation.
     */
    protected static function bootHasBinaryUuid(): void
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = Uuid::uuid7()->toString();
            }
        });
    }

    /**
     * Create a new Eloquent query builder for the model.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return \App\Builders\BinaryUuidBuilder
     */
    public function newEloquentBuilder($query)
    {
        return new BinaryUuidBuilder($query);
    }

    /**
     * Get the route key for the model.
     *
     * @return string
     */
    public function getRouteKeyName()
    {
        return 'uuid';
    }

    /**
     * Retrieve the model for a bound value.
     *
     * @param  mixed  $value
     * @param  string|null  $field
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function resolveRouteBinding($value, $field = null)
    {
        $field = $field ?? $this->getRouteKeyName();

        return $this->where($field, '=', $value)->first();
    }
}
