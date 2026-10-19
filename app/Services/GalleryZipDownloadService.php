<?php

namespace App\Services;

use App\Models\GalleryDownload;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class GalleryZipDownloadService
{
    /**
     * Checks if a ZIP download has expired (completed more than 24 hours ago).
     * If so, deletes the Backblaze B2 storage file, marks the status as 'expired',
     * updates the 'expired_at' column, and clears the 'storage_path'.
     */
    public function expireIfNecessary(GalleryDownload $download): bool
    {
        if ($download->status === 'expired' || $download->expired_at !== null) {
            return true;
        }

        if (!in_array($download->status, ['ready', 'ready_with_errors'])) {
            return false;
        }

        $expiresAt = $download->expiresAt();
        if ($expiresAt && $expiresAt->isPast()) {
            if ($download->storage_path) {
                try {
                    if (Storage::disk('b2')->exists($download->storage_path)) {
                        Storage::disk('b2')->delete($download->storage_path);
                    }
                } catch (\Throwable $e) {
                    Log::error('Failed to delete expired ZIP from storage.', [
                        'download_id' => $download->id,
                        'storage_path' => $download->storage_path,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $download->update([
                'status' => 'expired',
                'expired_at' => now(),
                'storage_path' => null,
            ]);

            return true;
        }

        return false;
    }
}
