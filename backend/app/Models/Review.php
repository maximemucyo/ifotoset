<?php

namespace App\Models;

use App\Casts\UuidBinaryCast;
use App\Traits\HasBinaryUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Review extends Model
{
    use SoftDeletes, HasBinaryUuid;

    protected $fillable = [
        'uuid',
        'user_id',
        'name',
        'quote',
        'rating',
        'detail',
        'is_approved',
    ];

    protected $casts = [
        'uuid' => UuidBinaryCast::class,
        'rating' => 'integer',
        'is_approved' => 'boolean',
    ];

    /**
     * Get the photographer user that owns the review.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
