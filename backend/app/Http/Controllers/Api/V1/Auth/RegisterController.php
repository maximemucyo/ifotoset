<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Ramsey\Uuid\Uuid;

class RegisterController extends Controller
{
    /**
     * Handle user signup request.
     * POST /api/v1/auth/register
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        // Retrieve or initialize the default free plan tier
        $freePlan = Plan::firstOrCreate([
            'slug' => 'free',
        ], [
            'uuid' => Uuid::uuid7()->toString(),
            'name' => 'Free Plan',
            'storage_limit' => 5 * 1024 * 1024 * 1024, // 5 GB
            'video_limit' => 0,
            'gallery_limit' => 3,
            'team_limit' => 0,
        ]);

        $user = User::create([
            'uuid' => Uuid::uuid7()->toString(),
            'plan_id' => $freePlan->id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        Auth::login($user);

        $storageStats = app(\App\Services\StorageStatisticsService::class)->getStorageStats($user);

        return response()->json([
            'data' => [
                'user' => [
                    'uuid' => $user->uuid,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'plan' => $freePlan->slug,
                    'storage' => $storageStats,
                ],
                'permissions' => ['galleries.manage'],
            ]
        ], 201);
    }
}
?>
