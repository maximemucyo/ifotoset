<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class GooglePhotoCredential extends Model
{
    use Prunable;

    protected $fillable = [
        'sync_id',
        'access_token',
        'refresh_token',
        'expires_at',
    ];

    protected $casts = [
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'expires_at' => 'datetime',
    ];

    public function sync(): BelongsTo
    {
        return $this->belongsTo(GooglePhotoSync::class, 'sync_id');
    }

    /**
     * Tokens are only needed for one export. Revoke the grant at Google and
     * drop the row as soon as the sync is finished, either way.
     */
    public function revoke(): void
    {
        $this->revokeAtGoogle();
        $this->delete();
    }

    /**
     * Safety net for syncs that never reached a final state (worker crash,
     * missing chunk): no export runs anywhere near a day.
     */
    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDay());
    }

    protected function pruning(): void
    {
        $this->revokeAtGoogle();
    }

    protected function revokeAtGoogle(): void
    {
        // Revoking the refresh token also invalidates its access tokens
        $token = $this->refresh_token ?: $this->access_token;

        try {
            $response = Http::asForm()->post('https://oauth2.googleapis.com/revoke', ['token' => $token]);

            // 400 means the token already expired or was revoked by the user
            if ($response->failed() && $response->status() !== 400) {
                Log::warning('Google Photos token revoke failed', [
                    'sync_id' => $this->sync_id,
                    'status' => $response->status(),
                ]);
            }
        } catch (Throwable $e) {
            Log::warning('Google Photos token revoke failed', [
                'sync_id' => $this->sync_id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
