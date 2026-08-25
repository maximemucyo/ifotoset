<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    /**
     * Handle stateful credentials check.
     * POST /api/v1/auth/login
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            $user = Auth::user();
            $storageStats = app(\App\Services\StorageStatisticsService::class)->getStorageStats($user);

            return response()->json([
                'data' => [
                    'user' => [
                        'uuid' => $user->uuid,
                        'name' => $user->name,
                        'username' => $user->username,
                        'email' => $user->email,
                        'role' => $user->role,
                        'plan' => $user->plan->slug ?? 'free',
                        'email_verified' => $user->email_verified_at !== null,
                        'storage' => $storageStats,
                    ],
                    'permissions' => $user->role === 'admin'
                        ? ['admin.access', 'galleries.manage']
                        : ['galleries.manage'],
                ]
            ], 200);
        }

        return response()->json([
            'code' => 'INVALID_CREDENTIALS',
            'message' => 'The provided credentials do not match our records.',
        ], 422);
    }
}
?>
