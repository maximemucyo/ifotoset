<?php

namespace App\Models;

use App\Casts\UuidBinaryCast;
use App\Traits\HasBinaryUuid;
use Illuminate\Database\Eloquent\Model;

class StorageDisk extends Model
{
    use HasBinaryUuid;
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
