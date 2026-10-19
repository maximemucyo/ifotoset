<?php

namespace Database\Seeders;

use App\Enums\MediaJobStatus;
use App\Enums\PhotoStatus;
use App\Jobs\ProcessPhotoJob;
use App\Models\AvailabilitySetting;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Gallery;
use App\Models\MediaJob;
use App\Models\Package;
use App\Models\Photo;
use App\Models\StorageDisk;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Ramsey\Uuid\Uuid;
use Throwable;

/**
 * Local/demo content: real photos (Unsplash-licensed via picsum.photos) pushed through
 * the actual storage + ProcessPhotoJob pipeline, plus clients, packages, bookings and
 * availability so every studio screen has realistic data.
 *
 * Run: php artisan db:seed --class=DemoContentSeeder
 * Requires the local S3 service (docker compose) and a running `photos` queue worker.
 */
class DemoContentSeeder extends Seeder
{
    /** Picsum image ids per seeded gallery slug, chosen to match each theme. */
    private const GALLERY_PHOTOS = [
        'wedding-sarah-john' => [129, 64, 65, 82, 106, 195, 110, 206, 152, 102, 112, 176],
        'corporate-event-tech-summit' => [158, 117, 180, 42, 192, 119, 60, 48],
        'portrait-session-january' => [64, 65, 91, 177, 22, 27, 173, 129],
        'engagement-emma-david' => [173, 129, 110, 206, 62, 176, 164, 195, 112, 152],
        'fashion-photoshoot' => [21, 64, 65, 22, 91, 133, 63, 145],
        'family-portraits-smiths' => [103, 169, 200, 129, 65, 177, 27, 64],
        'product-shoot-coffee-shop' => [30, 63, 42, 163, 102, 75, 113, 139],
        'maternity-session-jane-doe' => [65, 64, 106, 82, 152, 110, 109, 112],
        'graduation-shoot-kigali' => [193, 142, 164, 57, 91, 22, 173, 116],
    ];

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('DemoContentSeeder skipped in production.');
            return;
        }

        $user = User::where('email', 'studio@ifotoset.com')->first();
        if (!$user) {
            $this->command?->warn('Demo photographer not found; run UserSeeder first.');
            return;
        }

        $user->forceFill([
            'email_verified_at' => $user->email_verified_at ?? now(),
            'bio' => $user->bio ?? 'Wedding, portrait and editorial photographer telling honest, light-filled stories across East Africa.',
            'location' => $user->location ?? 'Kigali, Rwanda',
            'phone' => $user->phone ?? '+250 788 000 000',
            'website' => $user->website ?? 'https://sarahphoto.example',
        ])->save();

        User::where('email', 'admin@ifotoset.com')->whereNull('email_verified_at')->update(['email_verified_at' => now()]);

        $this->seedAvailability($user);
        $packages = $this->seedPackages($user);
        $clients = $this->seedClients($user);
        $this->seedBookings($user, $clients, $packages);
        $this->seedPhotos($user);
    }

    private function seedAvailability(User $user): void
    {
        $hours = [1 => ['09:00', '17:00'], 2 => ['09:00', '17:00'], 3 => ['09:00', '17:00'], 4 => ['09:00', '17:00'], 5 => ['09:00', '16:00'], 6 => ['10:00', '14:00']];
        foreach (range(0, 6) as $day) {
            AvailabilitySetting::updateOrCreate(
                ['user_id' => $user->id, 'day_of_week' => $day],
                ['start_time' => $hours[$day][0] ?? '09:00', 'end_time' => $hours[$day][1] ?? '17:00', 'is_active' => isset($hours[$day])]
            );
        }
        if (method_exists($user, 'clearAvailabilityCache')) {
            $user->clearAvailabilityCache();
        }
    }

    /** @return array<int, Package> */
    private function seedPackages(User $user): array
    {
        $items = [
            ['Portrait Session', 'A relaxed one-hour portrait session at a location of your choice.', 80000, 60, ['30 edited photos', 'Online gallery', '1 location'], 'percentage', 30],
            ['Engagement Story', 'Golden-hour couple session to celebrate your engagement.', 150000, 120, ['60 edited photos', 'Online gallery', '2 locations', 'Print release'], 'percentage', 30],
            ['Wedding Day', 'Full-day coverage from preparation to first dance.', 1200000, 600, ['500+ edited photos', 'Two photographers', 'Private online gallery', 'Highlight film'], 'fixed', 300000],
            ['Brand & Product', 'Commercial imagery for products, menus and campaigns.', 250000, 180, ['25 retouched images', 'Commercial licence', 'Studio or on-site'], 'percentage', 50],
        ];

        $packages = [];
        foreach ($items as $i => [$name, $description, $price, $duration, $deliverables, $depositType, $deposit]) {
            $packages[] = Package::updateOrCreate(
                ['user_id' => $user->id, 'name' => $name],
                [
                    'uuid' => Package::where('user_id', $user->id)->where('name', $name)->value('uuid') ?? Uuid::uuid7()->toString(),
                    'description' => $description,
                    'price' => $price,
                    'currency' => 'RWF',
                    'duration_minutes' => $duration,
                    'deliverables' => $deliverables,
                    'sort_order' => $i,
                    'is_active' => true,
                    'deposit_type' => $depositType,
                    'deposit_amount' => $deposit,
                ]
            );
        }

        return $packages;
    }

    /** @return array<int, Client> */
    private function seedClients(User $user): array
    {
        $items = [
            ['Sarah & John Mugisha', 'sarah.john@example.com', '+250 788 111 201', null, 'Kigali', ['wedding']],
            ['Emma & David Uwase', 'emma.david@example.com', '+250 788 111 202', null, 'Musanze', ['engagement']],
            ['Kigali Tech Summit', 'events@techsummit.example', '+250 788 111 203', 'Tech Summit Ltd', 'Kigali', ['corporate']],
            ['The Smith Family', 'smiths@example.com', '+250 788 111 204', null, 'Rubavu', ['family']],
            ['Question Coffee', 'hello@coffee.example', '+250 788 111 205', 'Question Coffee', 'Kigali', ['commercial']],
            ['Jane Doe', 'jane.doe@example.com', '+250 788 111 206', null, 'Huye', ['maternity']],
            ['Grace Ingabire', 'grace@example.com', '+250 788 111 207', null, 'Kigali', ['graduation']],
            ['Atelier Maison', 'studio@atelier.example', '+250 788 111 208', 'Atelier Maison', 'Kigali', ['fashion', 'commercial']],
        ];

        $clients = [];
        foreach ($items as $i => [$name, $email, $phone, $company, $location, $tags]) {
            $clients[] = Client::updateOrCreate(
                ['user_id' => $user->id, 'email' => $email],
                [
                    'uuid' => Client::where('user_id', $user->id)->where('email', $email)->value('uuid') ?? Uuid::uuid7()->toString(),
                    'name' => $name,
                    'phone' => $phone,
                    'company_name' => $company,
                    'location' => $location,
                    'tags' => $tags,
                    'last_contacted_at' => now()->subDays($i * 4 + 1),
                ]
            );
        }

        return $clients;
    }

    /**
     * @param array<int, Client> $clients
     * @param array<int, Package> $packages
     */
    private function seedBookings(User $user, array $clients, array $packages): void
    {
        if (Booking::where('user_id', $user->id)->exists()) {
            return;
        }

        $plan = [
            // [client idx, package idx, days from now, hour, status, location]
            [0, 2, -40, 10, 'completed', 'Kigali Serena Hotel'],
            [1, 1, -12, 16, 'completed', 'Lake Kivu, Rubavu'],
            [2, 3, -5, 9, 'completed', 'Kigali Convention Centre'],
            [3, 0, 3, 15, 'confirmed', 'Nyandungu Eco Park'],
            [6, 0, 5, 11, 'pending', 'University of Rwanda, Huye'],
            [7, 3, 8, 10, 'pending', 'Kimihurura studio'],
            [5, 0, 12, 14, 'confirmed', 'Client home, Kicukiro'],
            [1, 2, 45, 9, 'confirmed', 'Inema Arts Center'],
            [4, 3, 2, 8, 'cancelled', 'Question Coffee, Gishushu'],
        ];

        foreach ($plan as [$c, $p, $days, $hour, $status, $location]) {
            $package = $packages[$p];
            $starts = now()->startOfDay()->addDays($days)->setTime($hour, 0);
            Booking::create([
                'uuid' => Uuid::uuid7()->toString(),
                'user_id' => $user->id,
                'client_id' => $clients[$c]->id,
                'package_id' => $package->id,
                'title' => $package->name . ' · ' . $clients[$c]->name,
                'starts_at' => $starts,
                'ends_at' => (clone $starts)->addMinutes($package->duration_minutes),
                'timezone' => 'Africa/Kigali',
                'location' => $location,
                'status' => $status,
                'price' => $package->price,
                'currency' => 'RWF',
                'notes' => null,
            ]);
        }

        \Illuminate\Support\Facades\Cache::forget('studio:pending-bookings:' . $user->id);
    }

    private function seedPhotos(User $user): void
    {
        try {
            $client = Storage::disk('b2')->getClient();
            $bucket = config('filesystems.disks.b2.bucket');
            if (!$client->doesBucketExist($bucket)) {
                $client->createBucket(['Bucket' => $bucket]);
            }
        } catch (Throwable $e) {
            $this->command?->warn('Object storage unavailable, skipping demo photos: ' . $e->getMessage());
            return;
        }

        $disk = StorageDisk::firstOrCreate(['driver' => 'b2'], [
            'uuid' => Uuid::uuid7()->toString(),
            'bucket' => config('filesystems.disks.b2.bucket', 'ifotoset-media'),
            'region' => config('filesystems.disks.b2.region', 'us-east-005'),
            'cdn_domain' => config('filesystems.disks.b2.cdn_domain', 'cdn.ifotoset.com'),
        ]);

        $cache = [];

        foreach (self::GALLERY_PHOTOS as $slug => $ids) {
            $gallery = Gallery::where('user_id', $user->id)->where('slug', $slug)->first();
            if (!$gallery || $gallery->photos()->exists()) {
                continue;
            }

            $gallery->forceFill(['show_on_profile' => $gallery->visibility === 'public', 'allow_photo_downloads' => true, 'allow_gallery_downloads' => true])->save();

            $isProtected = $gallery->visibility !== 'public' || !empty($gallery->password_hash);
            $prefix = $isProtected ? "protected-galleries/{$gallery->uuid}/" : "galleries/{$gallery->uuid}/";
            $coverId = null;

            foreach ($ids as $index => $picsumId) {
                $portrait = $index % 3 === 1;
                [$w, $h] = $portrait ? [1100, 1650] : [1650, 1100];
                $cacheKey = "{$picsumId}-{$w}x{$h}";

                try {
                    $bytes = $cache[$cacheKey] ??= Http::timeout(60)->retry(2, 500)
                        ->get("https://picsum.photos/id/{$picsumId}/{$w}/{$h}.jpg")
                        ->throw()
                        ->body();
                } catch (Throwable $e) {
                    $this->command?->warn("Could not download demo image {$picsumId}: {$e->getMessage()}");
                    continue;
                }

                $photoUuid = Uuid::uuid7()->toString();
                $filename = sprintf('%s_%03d.jpg', str_replace('-', '_', $slug), $index + 1);
                $objectKey = "{$prefix}photos/{$photoUuid}/{$filename}";
                Storage::disk('b2')->put($objectKey, $bytes);

                $photo = Photo::create([
                    'uuid' => $photoUuid,
                    'gallery_id' => $gallery->id,
                    'disk_id' => $disk->id,
                    'media_type' => 'photo',
                    'path' => dirname($objectKey),
                    'filename' => $filename,
                    'original_filename' => 'IMG_' . (4200 + $picsumId * 7 + $index) . '.jpg',
                    'stored_filename' => $filename,
                    'original_path' => $objectKey,
                    'mime_type' => 'image/jpeg',
                    'size' => strlen($bytes),
                    'checksum' => base64_encode(hash('sha256', $bytes, true)),
                    'sort_order' => $index,
                    'status' => PhotoStatus::Processing->value,
                ]);

                MediaJob::create([
                    'photo_id' => $photo->id,
                    'job_name' => ProcessPhotoJob::class,
                    'job_type' => ProcessPhotoJob::class,
                    'status' => MediaJobStatus::Queued->value,
                    'progress' => 'Queued',
                ]);

                ProcessPhotoJob::dispatch($photo);
                $coverId ??= $photo->id;
            }

            if ($coverId) {
                $gallery->forceFill(['cover_photo_id' => $coverId])->save();
            }

            $this->command?->info("Seeded demo photos for {$slug}.");
        }
    }
}
