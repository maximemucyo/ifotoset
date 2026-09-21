<?php

namespace App\Services;

use App\Models\Gallery;
use App\Models\Photo;

class StorageKeyResolver
{
    /**
     * Standard responsive image variant suffixes.
     *
     * @var array<string>
     */
    protected array $variantSizes = ['xs', 'sm', 'md', 'lg', 'xl'];

    /**
     * Resolve exact B2 object keys and directory prefix for a photo.
     *
     * @return array{objects: array<string>, prefix: string}
     */
    public function resolvePhotoKeys(Photo $photo): array
    {
        $objects = [];
        $rawPath = trim((string) $photo->path, '/');

        // Determine if path itself has a file extension (legacy or direct path)
        $pathExtension = pathinfo($rawPath, PATHINFO_EXTENSION);
        if (!empty($pathExtension)) {
            $objects[] = $rawPath;
            $prefix = dirname($rawPath);
            $filename = basename($rawPath);
        } else {
            $prefix = $rawPath;
            $filename = $photo->filename ?: $photo->stored_filename;
            if ($filename) {
                $objects[] = "{$prefix}/{$filename}";
            }
        }

        // Add variants based on filename
        if ($filename) {
            $baseName = pathinfo($filename, PATHINFO_FILENAME);
            foreach ($this->variantSizes as $size) {
                $objects[] = "{$prefix}/{$baseName}_{$size}.webp";
            }

            // Also check stored_filename if different
            if ($photo->stored_filename && $photo->stored_filename !== $filename) {
                $storedBase = pathinfo($photo->stored_filename, PATHINFO_FILENAME);
                $objects[] = "{$prefix}/{$photo->stored_filename}";
                foreach ($this->variantSizes as $size) {
                    $objects[] = "{$prefix}/{$storedBase}_{$size}.webp";
                }
            }
        }

        return [
            'objects' => array_values(array_unique($objects)),
            'prefix' => $prefix,
        ];
    }

    /**
     * Resolve exact B2 object keys, photo prefixes, and top-level gallery prefix for a gallery.
     *
     * @return array{objects: array<string>, prefixes: array<string>, gallery_prefix: string}
     */
    public function resolveGalleryKeys(Gallery $gallery): array
    {
        $galleryUuid = (string) $gallery->uuid;
        $galleryPrefix = "galleries/{$galleryUuid}";

        $objects = [];
        $prefixes = [
            "{$galleryPrefix}/downloads",
        ];

        // Retrieve all photos including soft-deleted ones
        $photos = $gallery->photos()->withTrashed()->get();
        foreach ($photos as $photo) {
            $resolved = $this->resolvePhotoKeys($photo);
            $objects = array_merge($objects, $resolved['objects']);
            if (!empty($resolved['prefix'])) {
                $prefixes[] = $resolved['prefix'];
            }
        }

        $prefixes[] = $galleryPrefix;

        return [
            'objects' => array_values(array_unique($objects)),
            'prefixes' => array_values(array_unique($prefixes)),
            'gallery_prefix' => $galleryPrefix,
        ];
    }
}
