<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules;

class PasswordResetController extends Controller
{
    /**
     * Send a reset link to the given user.
     * POST /api/v1/auth/forgot-password
     */
    public function sendResetLinkEmail(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // Always return generic success response, regardless of password reset link status,
        // to prevent account/email enumeration.
        Password::sendResetLink(
            $request->only('email')
        );

        return response()->json([
            'message' => 'If an account exists for that email, a password reset link has been sent.',
        ], 200);
    }

    /**
     * Reset the given user's password.
     * POST /api/v1/auth/reset-password
     */
    public function reset(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'Your password has been reset.',
            ], 200);
        }

        return response()->json([
            'code' => 'INVALID_TOKEN_OR_EMAIL',
            'message' => 'This password reset link is invalid or has expired.',
        ], 422);
    }
}
