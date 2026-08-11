<?php

namespace Database\Seeders;

use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReviewSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $photographer = User::where('role', 'photographer')->first();
        if (!$photographer) {
            return;
        }

        $reviews = [
            [
                'name' => 'Aline & Eric',
                'quote' => 'The session felt completely natural. Every photo captures exactly who we are, beautifully.',
                'rating' => 5,
                'detail' => 'Couple Shoot',
            ],
            [
                'name' => 'Maya N.',
                'quote' => 'Incredible attention to detail. The final gallery brought our entire celebration back to life.',
                'rating' => 5,
                'detail' => 'Editorial session',
            ],
            [
                'name' => 'Chantal & Yves',
                'quote' => 'Warm, calm, and highly professional. We felt comfortable and guided throughout.',
                'rating' => 5,
                'detail' => 'Portrait session',
            ],
        ];

        foreach ($reviews as $rev) {
            Review::create([
                'user_id' => $photographer->id,
                'name' => $rev['name'],
                'quote' => $rev['quote'],
                'rating' => $rev['rating'],
                'detail' => $rev['detail'],
                'is_approved' => true,
            ]);
        }
    }
}
