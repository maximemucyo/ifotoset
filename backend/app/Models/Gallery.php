<?php

namespace App\Models;

use App\Casts\UuidBinaryCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Ramsey\Uuid\Uuid;

class Gallery extends Model
{
    use SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (Gallery $gallery) {
            if (empty($gallery->uuid)) {
                $gallery->uuid = Uuid::uuid7()->toString();
            }
        });
    }

    protected $fillable = [
        'uuid',
        'user_id',
        'cover_photo_id',
        'title',
        'slug',
        'client_name',
        'event_date',
        'visibility',
        'password_hash',
        'password_hint',
        'invite_token',
        'expires_at',
        'version',
    ];

    protected $casts = [
        'uuid' => UuidBinaryCast::class,
        'event_date' => 'date',
        'expires_at' => 'datetime',
        'version' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function stats(): HasOne
    {
        return $this->hasOne(GalleryStats::class, 'gallery_id');
    }

    public function coverPhoto(): BelongsTo
    {
        return $this->belongsTo(Photo::class, 'cover_photo_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(GalleryInvitation::class);
    }
}
?>
