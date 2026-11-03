<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GalleryFavorite extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'list_id',
        'photo_id',
    ];

    public function list(): BelongsTo
    {
        return $this->belongsTo(GalleryFavoriteList::class, 'list_id');
    }

    public function photo(): BelongsTo
    {
        return $this->belongsTo(Photo::class);
    }
}
