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
}
