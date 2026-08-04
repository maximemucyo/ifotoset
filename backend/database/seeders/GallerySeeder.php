<?php

namespace Database\Seeders;

use App\Models\Gallery;
use App\Models\GalleryStats;
use App\Models\User;
use Illuminate\Database\Seeder;
use Ramsey\Uuid\Uuid;

class GallerySeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'studio@ifotoset.com')->first();
        if (!$user) {
            return;
        }

        $items = [
            [
                'title' => 'Wedding - Sarah & John',
                'slug' => 'wedding-sarah-john',
                'client_name' => 'Sarah & John',
                'event_date' => '2024-01-15',
                'visibility' => 'public',
                'password' => null,
                'photo_count' => 245,
                'total_bytes' => 8427382947,
                'downloads' => 125,
                'favorites' => 180,
            ],
            [
                'title' => 'Corporate Event - Tech Summit',
                'slug' => 'corporate-event-tech-summit',
                'client_name' => 'Tech Company',
                'event_date' => '2024-01-14',
                'visibility' => 'public',
                'password' => null,
                'photo_count' => 128,
                'total_bytes' => 4509715200,
                'downloads' => 42,
                'favorites' => 55,
            ],
            [
                'title' => 'Portrait Session - January',
                'slug' => 'portrait-session-january',
                'client_name' => 'Various',
                'event_date' => '2024-01-13',
                'visibility' => 'private',
                'password' => 'secret123',
                'photo_count' => 89,
                'total_bytes' => 2254857830,
                'downloads' => 15,
                'favorites' => 30,
            ],
            [
                'title' => 'Engagement - Emma & David',
                'slug' => 'engagement-emma-david',
                'client_name' => 'Emma & David',
                'event_date' => '2024-01-12',
                'visibility' => 'public',
                'password' => null,
                'photo_count' => 412,
                'total_bytes' => 13743895347,
                'downloads' => 312,
                'favorites' => 245,
            ],
            [
                'title' => 'Fashion Photoshoot',
                'slug' => 'fashion-photoshoot',
                'client_name' => 'Fashion Brand',
                'event_date' => '2024-01-11',
                'visibility' => 'public',
                'password' => null,
                'photo_count' => 256,
                'total_bytes' => 7194070220,
                'downloads' => 88,
                'favorites' => 110,
            ],
            [
                'title' => 'Family Portraits - The Smiths',
                'slug' => 'family-portraits-smiths',
                'client_name' => 'The Smiths',
                'event_date' => '2024-01-08',
                'visibility' => 'private',
                'password' => 'smiths2024',
                'photo_count' => 45,
                'total_bytes' => 1288490188,
                'downloads' => 10,
                'favorites' => 18,
            ],
            [
                'title' => 'Product Shoot - Coffee Shop',
                'slug' => 'product-shoot-coffee-shop',
                'client_name' => 'Local Brews',
                'event_date' => '2024-01-05',
                'visibility' => 'public',
                'password' => null,
                'photo_count' => 65,
                'total_bytes' => 1932735283,
                'downloads' => 24,
                'favorites' => 38,
            ],
            [
                'title' => 'Maternity Session - Jane Doe',
                'slug' => 'maternity-session-jane-doe',
                'client_name' => 'Jane Doe',
                'event_date' => '2024-01-02',
                'visibility' => 'private',
                'password' => null,
                'photo_count' => 0,
                'total_bytes' => 0,
                'downloads' => 0,
                'favorites' => 0,
            ],
            [
                'title' => 'Graduation Shoot - Kigali',
                'slug' => 'graduation-shoot-kigali',
                'client_name' => 'Kigali University',
                'event_date' => '2023-12-20',
                'visibility' => 'public',
                'password' => null,
                'photo_count' => 180,
                'total_bytes' => 5905580032,
                'downloads' => 95,
                'favorites' => 140,
            ]
        ];

        foreach ($items as $item) {
            $gallery = Gallery::firstOrCreate([
                'user_id' => $user->id,
                'slug' => $item['slug'],
            ], [
                'uuid' => Uuid::uuid7()->toString(),
                'title' => $item['title'],
                'client_name' => $item['client_name'],
                'event_date' => $item['event_date'],
                'visibility' => $item['visibility'],
                'password_hash' => $item['password'] ? bcrypt($item['password']) : null,
                'password_hint' => $item['password'] ? 'Hint: ' . substr($item['password'], 0, 2) . '...' : null,
                'version' => 1,
            ]);

            GalleryStats::firstOrCreate([
                'gallery_id' => $gallery->id,
            ], [
                'photo_count' => $item['photo_count'],
                'video_count' => 0,
                'downloads_count' => $item['downloads'],
                'favorites_count' => $item['favorites'],
                'total_bytes' => $item['total_bytes'],
            ]);
        }
    }
}
