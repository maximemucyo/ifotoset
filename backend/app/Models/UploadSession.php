<?php

namespace App\Models;

use App\Casts\UuidBinaryCast;
use Illuminate\Database\Eloquent\Model;

class UploadSession extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'gallery_id',
        'idempotency_key',
        'object_key',
        'expected_size',
        'expected_sha256',
        'status',
        'expires_at',
    ];

    protected $casts = [
        'uuid' => UuidBinaryCast::class,
        'expected_size' => 'integer',
        'expires_at' => 'datetime',
    ];
}
?>
