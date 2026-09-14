<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Request;

class AdminAuditLog extends Model
{
    public $timestamps = false;

    protected $table = 'admin_audit_logs';

    protected $fillable = [
        'admin_user_id',
        'action',
        'target_type',
        'target_id',
        'metadata',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Prevent updates to ensure append-only audit trail integrity.
     */
    public function update(array $attributes = [], array $options = [])
    {
        throw new \BadMethodCallException('Admin audit logs are append-only and cannot be updated.');
    }

    /**
     * Prevent deletes to ensure append-only audit trail integrity.
     */
    public function delete()
    {
        throw new \BadMethodCallException('Admin audit logs are append-only and cannot be deleted.');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }

    /**
     * Record an administrative event atomically.
     */
    public static function record(
        User $admin,
        string $action,
        ?string $targetType = null,
        ?string $targetId = null,
        array $metadata = []
    ): self {
        return self::create([
            'admin_user_id' => $admin->id,
            'action'        => $action,
            'target_type'   => $targetType,
            'target_id'     => $targetId,
            'metadata'      => $metadata ?: null,
            'ip_address'    => Request::ip(),
            'user_agent'    => substr((string) Request::userAgent(), 0, 500),
            'created_at'    => now(),
        ]);
    }
}
