<?php

namespace App\Support;

use App\Models\Photo;
use Illuminate\Support\Facades\Storage;

/**
 * Sends one photo or video as a download under its original file name.
 */
class PhotoDownload
{
    public static function response(Photo $photo)
    {
        $filename = $photo->original_filename ?: ($photo->filename ?: 'photo.jpg');
        if ($photo->isVideo()) {
            $storagePath = $photo->delivery_path ?: $photo->original_path ?: ltrim($photo->path . '/' . ($photo->filename ?? $photo->stored_filename), '/');
            if (!str_ends_with(strtolower($filename), '.mp4')) {
                $filename = pathinfo($filename, PATHINFO_FILENAME) . '.mp4';
            }
        } else {
            $storagePath = ltrim($photo->path . '/' . ($photo->filename ?? $photo->stored_filename), '/');
        }

        // Check if running on local storage disk or mocked storage without S3 credentials
        if (!config('filesystems.disks.b2.key')) {
            if (Storage::disk('b2')->exists($storagePath)) {
                return Storage::disk('b2')->download($storagePath, $filename);
            }
        }

        $storageService = app(\App\Services\StorageService::class);
        $presignedUrl = $storageService->generatePresignedDownloadUrl($storagePath, $filename, now()->addMinutes(15));

        return redirect()->away($presignedUrl, 302, [
            'Referrer-Policy' => 'no-referrer',
        ]);
    }
}
