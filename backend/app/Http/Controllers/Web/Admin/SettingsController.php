<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\SystemSetting;
use App\Services\SmtpSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class SettingsController extends Controller
{
    /**
     * Display platform configuration settings (SMTP & General).
     */
    public function index(SmtpSettingsService $smtpService): View
    {
        $smtp = $smtpService->getSafeSettings();

        $general = [
            'platform_name'       => SystemSetting::getOption('platform_name', config('app.name', 'ifotoset')),
            'support_email'       => SystemSetting::getOption('support_email', 'support@ifotoset.com'),
            'support_phone'       => SystemSetting::getOption('support_phone', '+250 788 000 000'),
            'default_currency'    => SystemSetting::getOption('default_currency', 'RWF'),
            'momo_merchant_code'  => SystemSetting::getOption('momo_merchant_code', ''),
            'momo_account_name'   => SystemSetting::getOption('momo_account_name', ''),
            'announcement_banner' => SystemSetting::getOption('announcement_banner', ''),
            'maintenance_notice'  => SystemSetting::getOption('maintenance_notice', ''),
        ];

        return view('admin.settings', compact('smtp', 'general'));
    }

    /**
     * Update SMTP mail server settings with encrypted credential storage.
     */
    public function updateSmtp(Request $request, SmtpSettingsService $smtpService): RedirectResponse
    {
        $validated = $request->validate([
            'host'         => ['required', 'string', 'max:255'],
            'port'         => ['required', 'integer', 'between:1,65535'],
            'username'     => ['required', 'string', 'max:255'],
            'password'     => ['nullable', 'string', 'max:500'],
            'encryption'   => ['required', 'string', 'in:tls,ssl,none'],
            'from_address' => ['required', 'email', 'max:255'],
            'from_name'    => ['required', 'string', 'max:255'],
        ]);

        $result = $smtpService->updateSettings($validated);

        AdminAuditLog::record(
            $request->user(),
            'settings.smtp_updated',
            'SystemSetting',
            'smtp',
            [
                'host'             => $validated['host'],
                'port'             => $validated['port'],
                'username'         => $validated['username'],
                'encryption'       => $validated['encryption'],
                'from_address'     => $validated['from_address'],
                'from_name'        => $validated['from_name'],
                'password_updated' => $result['password_updated'],
            ]
        );

        return back()->with('toast', [
            'type'    => 'success',
            'message' => 'SMTP mail server configuration updated and loaded successfully.',
        ]);
    }

    /**
     * Send a live test email using current SMTP configuration.
     */
    public function testSmtp(Request $request, SmtpSettingsService $smtpService): RedirectResponse
    {
        $validated = $request->validate([
            'test_email' => ['required', 'email', 'max:255'],
        ]);

        $recipient = $validated['test_email'];

        try {
            $smtpService->loadMailSettings();

            Mail::raw(
                "Hello from ifotoset!\n\nThis is a verified test email sent from your ifotoset administrative console.\nTimestamp: " . now()->toRfc850String() . "\nEnvironment: " . config('app.env'),
                function ($message) use ($recipient) {
                    $message->to($recipient)->subject('ifotoset SMTP Configuration Test');
                }
            );

            AdminAuditLog::record(
                $request->user(),
                'settings.smtp_test_sent',
                'SystemSetting',
                'smtp',
                ['recipient' => $recipient]
            );

            return back()->with('toast', [
                'type'    => 'success',
                'message' => "Test email successfully sent to {$recipient}.",
            ]);
        } catch (\Throwable $e) {
            Log::error('SMTP Test email failed in Admin Settings: ' . $e->getMessage(), [
                'exception' => $e,
            ]);

            $lower = strtolower($e->getMessage());
            if (str_contains($lower, 'authentication') || str_contains($lower, 'credentials') || str_contains($lower, 'username')) {
                $errorMsg = 'SMTP authentication failed. Verify username and password credentials.';
            } elseif (str_contains($lower, 'connect') || str_contains($lower, 'timeout') || str_contains($lower, 'resolve')) {
                $errorMsg = 'SMTP connection failed. Check host, port, and SSL/TLS configuration.';
            } else {
                $errorMsg = 'Failed to deliver test email: ' . $e->getMessage();
            }

            return back()->with('toast', [
                'type'    => 'error',
                'message' => $errorMsg,
            ]);
        }
    }

    /**
     * Update general platform and regional media house settings.
     */
    public function updateGeneral(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'platform_name'       => ['required', 'string', 'max:100'],
            'support_email'       => ['required', 'email', 'max:255'],
            'support_phone'       => ['nullable', 'string', 'max:50'],
            'default_currency'    => ['required', 'string', 'in:RWF,USD,EUR'],
            'momo_merchant_code'  => ['nullable', 'string', 'max:50'],
            'momo_account_name'   => ['nullable', 'string', 'max:100'],
            'announcement_banner' => ['nullable', 'string', 'max:500'],
            'maintenance_notice'  => ['nullable', 'string', 'max:500'],
        ]);

        foreach ($validated as $key => $value) {
            SystemSetting::setOption($key, $value);
        }

        AdminAuditLog::record(
            $request->user(),
            'settings.general_updated',
            'SystemSetting',
            'general',
            $validated
        );

        return back()->with('toast', [
            'type'    => 'success',
            'message' => 'Platform general configuration updated successfully.',
        ]);
    }

    /**
     * Update administrator password.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::defaults()],
        ]);

        $admin = $request->user();

        \Illuminate\Support\Facades\DB::transaction(function () use ($admin, $validated, $request) {
            $admin->update([
                'password' => \Illuminate\Support\Facades\Hash::make($validated['password']),
            ]);

            AdminAuditLog::record(
                $admin,
                'admin.password_updated',
                'User',
                (string) $admin->id,
                [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]
            );
        });

        // Invalidate all other active sessions across other devices
        \Illuminate\Support\Facades\Auth::logoutOtherDevices($validated['password']);
        $request->session()->regenerate();

        return back()->with('toast', [
            'type'    => 'success',
            'message' => 'Admin password changed successfully. Other active sessions have been invalidated.',
        ]);
    }

    /**
     * Request a 6-digit verification code to change administrator login email.
     */
    public function requestEmailVerificationCode(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'new_email'        => ['required', 'email:filter', 'max:255', 'unique:users,email'],
        ]);

        $admin = $request->user();
        $newEmail = strtolower(trim($validated['new_email']));

        // Rate limiting: 60-second cooldown per admin
        $cooldownKey = "admin-email-otp-cooldown:{$admin->id}";
        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($cooldownKey, 1)) {
            $seconds = \Illuminate\Support\Facades\RateLimiter::availableIn($cooldownKey);
            return back()->withErrors([
                'new_email' => "Please wait {$seconds} seconds before requesting another verification code.",
            ]);
        }

        // Global throttle: max 5 requests per 15 minutes
        $maxAttemptsKey = "admin-email-otp-max:{$admin->id}";
        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($maxAttemptsKey, 5)) {
            return back()->withErrors([
                'new_email' => 'Too many verification code requests. Please try again in 15 minutes.',
            ]);
        }

        \Illuminate\Support\Facades\RateLimiter::hit($cooldownKey, 60);
        \Illuminate\Support\Facades\RateLimiter::hit($maxAttemptsKey, 900);

        // Generate 6-digit numeric OTP and store with keyed HMAC
        $code = sprintf('%06d', random_int(100000, 999999));
        $otpHmac = hash_hmac('sha256', $code, config('app.key'));

        \Illuminate\Support\Facades\Cache::put("admin_email_change:{$admin->id}", [
            'new_email' => $newEmail,
            'otp_hash'  => $otpHmac,
            'attempts'  => 0,
        ], now()->addMinutes(15));

        \Illuminate\Support\Facades\Mail::to($newEmail)->send(
            new \App\Mail\AdminEmailVerificationCodeMail($admin, $code, $newEmail)
        );

        return back()->with([
            'email_otp_sent' => true,
            'target_new_email' => $newEmail,
            'toast' => [
                'type'    => 'success',
                'message' => "Verification code sent to {$newEmail}. Code expires in 15 minutes.",
            ],
        ]);
    }

    /**
     * Verify OTP code and update administrator email address.
     */
    public function verifyAndChangeEmail(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'regex:/^[0-9]{6}$/'],
        ]);

        $admin = $request->user();
        $cacheKey = "admin_email_change:{$admin->id}";
        $cached = \Illuminate\Support\Facades\Cache::get($cacheKey);

        if (!$cached) {
            return back()->withErrors([
                'code' => 'Verification code expired or not requested. Please request a new code.',
            ]);
        }

        if (($cached['attempts'] ?? 0) >= 5) {
            \Illuminate\Support\Facades\Cache::forget($cacheKey);
            return back()->withErrors([
                'code' => 'Too many incorrect attempts. Verification code invalidated for security. Please request a new code.',
            ]);
        }

        $inputHmac = hash_hmac('sha256', $validated['code'], config('app.key'));
        if (!hash_equals($cached['otp_hash'], $inputHmac)) {
            $cached['attempts'] = ($cached['attempts'] ?? 0) + 1;
            \Illuminate\Support\Facades\Cache::put($cacheKey, $cached, now()->addMinutes(15));

            $remaining = 5 - $cached['attempts'];
            return back()->with([
                'email_otp_sent' => true,
                'target_new_email' => $cached['new_email'],
            ])->withErrors([
                'code' => "Invalid verification code. {$remaining} attempt(s) remaining.",
            ]);
        }

        // Code verified! Execute atomic email transition & audit logging
        $newEmail = $cached['new_email'];
        $oldEmail = $admin->email;

        \Illuminate\Support\Facades\DB::transaction(function () use ($admin, $newEmail, $oldEmail, $request) {
            $admin->update([
                'email'             => $newEmail,
                'email_verified_at' => now(),
            ]);

            AdminAuditLog::record(
                $admin,
                'admin.email_updated',
                'User',
                (string) $admin->id,
                [
                    'old_email'  => $oldEmail,
                    'new_email'  => $newEmail,
                    'ip'         => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]
            );
        });

        \Illuminate\Support\Facades\Cache::forget($cacheKey);
        $request->session()->regenerate();

        return back()->with('toast', [
            'type'    => 'success',
            'message' => "Administrator login email successfully updated to {$newEmail}.",
        ]);
    }
}
