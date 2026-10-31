<?php

namespace App\Console\Commands;

use App\Models\Photo;
use App\Models\PhotoMetadata;
use App\Support\ExifReader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class BackfillPhotoExif extends Command
{
    /**
     * EXIF lives in the JPEG header (APP1 is capped at 64 KB), so the start of the file is enough.
     */
    private const HEADER_BYTES = 256 * 1024;

    protected $signature = 'photos:backfill-exif
        {--gallery= : Only photos in this gallery id}
        {--dry-run : Read and report capture dates without saving them}';

    protected $description = 'Read capture date and camera details for photos processed while EXIF reading was unavailable';

    public function handle(): int
    {
        if (!function_exists('exif_read_data')) {
            $this->error('The PHP exif extension is not loaded.');

            return self::FAILURE;
        }

        $query = Photo::query()
            ->whereNull('taken_at')
            ->where(fn ($q) => $q->where('media_type', 'photo')->orWhereNull('media_type'))
            ->when($this->option('gallery'), fn ($q, $id) => $q->where('gallery_id', $id));

        $total = (clone $query)->count();
        $dated = 0;
        $failed = 0;
        $tempFile = tempnam(sys_get_temp_dir(), 'exif');

        $this->info("Checking {$total} photo(s)...");
        $bar = $this->output->createProgressBar($total);

        try {
            $query->chunkById(100, function ($photos) use ($tempFile, $bar, &$dated, &$failed) {
                foreach ($photos as $photo) {
                    $bar->advance();

                    try {
                        $exif = $this->readHeader($photo, $tempFile);
                    } catch (Throwable $e) {
                        $failed++;
                        $this->newLine();
                        $this->warn("#{$photo->id} {$photo->original_filename}: {$e->getMessage()}");
                        continue;
                    }

                    if ($exif === null || $exif['taken_at'] === null) {
                        continue;
                    }

                    $dated++;
                    if ($this->option('dry-run')) {
                        continue;
                    }

                    // Variants were already rotated at processing time, so orientation is left alone.
                    $photo->update(['taken_at' => $exif['taken_at']]);
                    PhotoMetadata::updateOrCreate(['photo_id' => $photo->id], [
                        'camera' => $exif['camera'],
                        'lens' => $exif['lens'],
                        'iso' => $exif['iso'],
                        'shutter_speed' => $exif['shutter_speed'],
                        'aperture' => $exif['aperture'],
                        'focal_length' => $exif['focal_length'],
                        'flash' => $exif['flash'],
                    ]);
                }
            });
        } finally {
            @unlink($tempFile);
        }

        $bar->finish();
        $this->newLine();

        $verb = $this->option('dry-run') ? 'would get' : 'got';
        $this->info("{$dated} of {$total} photo(s) {$verb} a capture date; {$failed} could not be read.");

        return self::SUCCESS;
    }

    /**
     * Copy the first bytes of the original to a local file and parse its EXIF.
     */
    private function readHeader(Photo $photo, string $tempFile): ?array
    {
        $key = ltrim($photo->original_path ?: $photo->path . '/' . ($photo->filename ?? $photo->stored_filename), '/');
        $stream = Storage::disk('b2')->readStream($key);
        if (!$stream) {
            return null;
        }

        try {
            $header = '';
            while (strlen($header) < self::HEADER_BYTES && !feof($stream)) {
                $chunk = fread($stream, self::HEADER_BYTES - strlen($header));
                if ($chunk === false || $chunk === '') {
                    break;
                }
                $header .= $chunk;
            }
        } finally {
            fclose($stream);
        }

        file_put_contents($tempFile, $header);

        return ExifReader::read($tempFile);
    }
}
