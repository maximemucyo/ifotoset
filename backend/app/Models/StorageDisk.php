<?php

namespace App\Models;

use App\Casts\UuidBinaryCast;
use Illuminate\Database\Eloquent\Model;

class StorageDisk extends Model
{
    protected $fillable = [
        'uuid',
        'driver',
        'bucket',
        'region',
        'cdn_domain',
    ];

    protected $casts = [
        'uuid' => UuidBinaryCast::class,
    ];
}
?>
