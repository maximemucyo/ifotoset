<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicReviewController extends Controller
{
    /**
     * Fetch approved reviews for a photographer.
     *
     * GET /api/v1/public/photographers/{username}/reviews
     */
    public function index(string $username): JsonResponse
    {
        $photographer = User::where('username', strtolower($username))->firstOrFail();

        $reviews = Review::where('user_id', $photographer->id)
            ->where('is_approved', true)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function (Review $review) {
                return [
                    'uuid' => $review->uuid,
                    'name' => $review->name,
                    'quote' => $review->quote,
                    'rating' => $review->rating,
                    'detail' => $review->detail,
                    'date' => $review->created_at->toIso8601String(),
                ];
            });

        return response()->json([
            'data' => $reviews,
        ]);
    }

    /**
     * Submit a review for a photographer.
     *
     * POST /api/v1/public/photographers/{username}/reviews
     */
    public function store(Request $request, string $username): JsonResponse
    {
        // Honeypot: reject if bots fill the _h field
        if ($request->filled('_h')) {
            return response()->json(['message' => 'Invalid request.'], 422);
        }

        $photographer = User::where('username', strtolower($username))->firstOrFail();

        $validated = $request->validate([
            'name'   => ['required', 'string', 'max:255'],
            'quote'  => ['required', 'string', 'max:5000'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'detail' => ['nullable', 'string', 'max:255'],
            '_h'     => ['sometimes', 'string', 'max:0'], // honeypot
        ]);

        $review = Review::create([
            'user_id' => $photographer->id,
            'name'    => $validated['name'],
            'quote'   => $validated['quote'],
            'rating'  => $validated['rating'],
            'detail'  => $validated['detail'] ?? null,
            'is_approved' => true,
        ]);

        return response()->json([
            'message' => 'Review submitted successfully.',
            'data' => [
                'uuid' => $review->uuid,
                'name' => $review->name,
                'quote' => $review->quote,
                'rating' => $review->rating,
                'detail' => $review->detail,
                'date' => $review->created_at->toIso8601String(),
            ],
        ], 201);
    }
}
