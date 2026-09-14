<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class SmtpSettingsService
{
    /**
     * Load SMTP configuration dynamically, override Laravel Mail settings, and purge cached mailer.
     */
    public function loadMailSettings(): void
    {
        $host = SystemSetting::getOption('smtp_host', config('mail.mailers.smtp.host'));
        $port = SystemSetting::getOption('smtp_port', config('mail.mailers.smtp.port'));
        $username = SystemSetting::getOption('smtp_username', config('mail.mailers.smtp.username'));
        $encryption = SystemSetting::getOption('smtp_encryption', config('mail.mailers.smtp.encryption'));
        $fromAddress = SystemSetting::getOption('smtp_from_address', config('mail.from.address'));
        $fromName = SystemSetting::getOption('smtp_from_name', config('mail.from.name'));

        // Retrieve and decrypt password
        $rawPassword = SystemSetting::getOption('smtp_password');
        $password = null;
        if ($rawPassword !== null && $rawPassword !== '') {
            try {
                $password = Crypt::decryptString($rawPassword);
            } catch (\Throwable $e) {
                // If stored in legacy plaintext, fall back gracefully
                $password = $rawPassword;
            }
        }

        if ($password === null) {
            $password = config('mail.mailers.smtp.password');
        }

        config([
            'mail.mailers.smtp.host'       => $host,
            'mail.mailers.smtp.port'       => $port,
            'mail.mailers.smtp.username'   => $username,
            'mail.mailers.smtp.password'   => $password,
            'mail.mailers.smtp.encryption' => $encryption,
            'mail.from.address'            => $fromAddress ?: $username,
            'mail.from.name'               => $fromName ?: config('app.name', 'ifotoset'),
        ]);

        // Purge Symfony SMTP transport cache so newly configured settings take effect immediately
        if (app()->bound('mail.manager')) {
            try {
                app('mail.manager')->purge('smtp');
            } catch (\Throwable $e) {
                Log::warning('Could not purge mail.manager smtp: ' . $e->getMessage());
            }
        }
    }

    /**
     * Get sanitized settings suitable for presentation in Blade (never exposes secret).
     */
    public function getSafeSettings(): array
    {
        $rawPassword = SystemSetting::getOption('smtp_password');
        $hasPassword = ! empty($rawPassword) || ! empty(config('mail.mailers.smtp.password'));

        return [
            'host'         => SystemSetting::getOption('smtp_host', config('mail.mailers.smtp.host', '')),
            'port'         => SystemSetting::getOption('smtp_port', config('mail.mailers.smtp.port', 587)),
            'username'     => SystemSetting::getOption('smtp_username', config('mail.mailers.smtp.username', '')),
            'encryption'   => SystemSetting::getOption('smtp_encryption', config('mail.mailers.smtp.encryption', 'tls')),
            'from_address' => SystemSetting::getOption('smtp_from_address', config('mail.from.address', '')),
            'from_name'    => SystemSetting::getOption('smtp_from_name', config('mail.from.name', 'ifotoset')),
            'has_password' => $hasPassword,
        ];
    }

    /**
     * Persist new SMTP settings, encrypt secret, and reload configuration.
     */
    public function updateSettings(array $validated): array
    {
        SystemSetting::setOption('smtp_host', $validated['host']);
        SystemSetting::setOption('smtp_port', (string) $validated['port']);
        SystemSetting::setOption('smtp_username', $validated['username']);
        SystemSetting::setOption('smtp_encryption', $validated['encryption'] ?? 'tls');
        SystemSetting::setOption('smtp_from_address', $validated['from_address']);
        SystemSetting::setOption('smtp_from_name', $validated['from_name']);

        $passwordUpdated = false;
        if (
            array_key_exists('password', $validated) &&
            $validated['password'] !== null &&
            $validated['password'] !== '********' &&
            $validated['password'] !== '••••••••••••' &&
            $validated['password'] !== ''
        ) {
            // Encrypt secret securely
            SystemSetting::setOption('smtp_password', Crypt::encryptString($validated['password']));
            $passwordUpdated = true;
        }

        // Apply to current runtime process
        $this->loadMailSettings();

        // Signal long-lived queue workers to restart and load fresh config on their next cycle
        try {
            Artisan::call('queue:restart');
        } catch (\Throwable $e) {
            Log::error('Failed to restart queue workers during SMTP settings update: ' . $e->getMessage());
        }

        return [
            'password_updated' => $passwordUpdated,
        ];
    }
}
