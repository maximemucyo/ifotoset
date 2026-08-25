<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class VerificationController extends Controller
{
    /**
     * Mark the user's email address as verified.
     */
    public function verify(Request $request, $id, $hash)
    {
        $user = User::findOrFail($id);

        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return redirect(config('app.frontend_url') . '/login?verified=0&error=invalid');
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        return redirect(config('app.frontend_url') . '/studio/dashboard?verified=1');
    }

    /**
     * Resend the email verification notification.
     */
    public function resend(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'code' => 'EMAIL_ALREADY_VERIFIED',
                'message' => 'Email is already verified.'
            ], 400);
        }

        $throttleKey = 'resend-verification:' . $user->id;
        $hourlyKey = 'resend-verification-hourly:' . $user->id;

        // 60-second limit check
        if (Cache::has($throttleKey)) {
            $seconds = Cache::ttl($throttleKey);
            return response()->json([
                'code' => 'RATE_LIMITED',
                'message' => "Please wait {$seconds} seconds before requesting another verification email."
            ], 429);
        }

        // Hourly limit check (max 5 per hour)
        $hourlyCount = Cache::get($hourlyKey, 0);
        if ($hourlyCount >= 5) {
            $seconds = Cache::ttl($hourlyKey);
            $minutes = ceil($seconds / 60);
            return response()->json([
                'code' => 'RATE_LIMITED',
                'message' => "Too many verification requests. Please try again in {$minutes} minutes."
            ], 429);
        }

        try {
            $user->sendEmailVerificationNotification();
        } catch (\Exception $e) {
            Log::error('Verification email delivery failed: ' . $e->getMessage());
            return response()->json([
                'code' => 'EMAIL_DELIVERY_FAILED',
                'message' => 'Failed to send verification email. Please try again later.'
            ], 500);
        }

        // Set 60-second throttle
        Cache::put($throttleKey, true, 60);

        // Increment hourly count
        Cache::put($hourlyKey, $hourlyCount + 1, now()->addHour());

        return response()->json([
            'message' => 'Verification link sent to your email.'
        ], 200);
    }
}
