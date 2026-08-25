<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Auth\Events\Registered;
use Ramsey\Uuid\Uuid;

class RegisterController extends Controller
{
    /**
     * Handle user signup request.
     * POST /api/v1/auth/register
     */
    public function register(Request $request): JsonResponse
    {
        if ($request->has('username')) {
            $request->merge([
                'username' => $request->username ? strtolower(trim($request->username)) : null,
            ]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required',
                'string',
                'regex:/^[a-z0-9](?:[a-z0-9-]{1,48}[a-z0-9])?$/',
                'unique:users,username',
                Rule::notIn(config('reserved_usernames', []))
            ],
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
            'username' => $validated['username'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        // Dispatch Registered event (this will queue the QueuedVerifyEmail notification)
        event(new Registered($user));

        Auth::login($user);

        $storageStats = app(\App\Services\StorageStatisticsService::class)->getStorageStats($user);

        return response()->json([
            'data' => [
                'user' => [
                    'uuid' => $user->uuid,
                    'name' => $user->name,
                    'username' => $user->username,
                    'email' => $user->email,
                    'role' => $user->role,
                    'plan' => $freePlan->slug,
                    'email_verified' => false,
                    'storage' => $storageStats,
                ],
                'permissions' => ['galleries.manage'],
            ]
        ], 201);
    }

    /**
     * Check if a username is available.
     * GET /api/v1/auth/check-username
     */
    public function checkUsername(Request $request): JsonResponse
    {
        $username = $request->query('username') ? strtolower(trim($request->query('username'))) : '';

        $validator = \Illuminate\Support\Facades\Validator::make(['username' => $username], [
            'username' => [
                'required',
                'string',
                'regex:/^[a-z0-9](?:[a-z0-9-]{1,48}[a-z0-9])?$/',
                'unique:users,username',
                Rule::notIn(config('reserved_usernames', []))
            ]
        ]);

        return response()->json([
            'available' => !$validator->fails()
        ], 200);
    }
}
?>
