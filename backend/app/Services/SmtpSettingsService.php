<?php

namespace App\Services;

use App\Models\SystemSetting;

class SmtpSettingsService
{
    /**
     * Load SMTP configuration dynamically and override Laravel Mail settings.
     */
    public function loadMailSettings(): void
    {
        $host = SystemSetting::getOption('smtp_host', config('mail.mailers.smtp.host'));
        $port = SystemSetting::getOption('smtp_port', config('mail.mailers.smtp.port'));
        $username = SystemSetting::getOption('smtp_username', config('mail.mailers.smtp.username'));
        $encryption = SystemSetting::getOption('smtp_encryption', config('mail.mailers.smtp.encryption'));
        $fromAddress = SystemSetting::getOption('smtp_from_address', config('mail.from.address'));
        $fromName = SystemSetting::getOption('smtp_from_name', config('mail.from.name'));

        // Fall back to config/env value if DB is empty
        $password = SystemSetting::getOption('smtp_password');
        if ($password === null) {
            $password = config('mail.mailers.smtp.password');
        }

        config([
            'mail.mailers.smtp.host' => $host,
            'mail.mailers.smtp.port' => $port,
            'mail.mailers.smtp.username' => $username,
            'mail.mailers.smtp.password' => $password,
            'mail.mailers.smtp.encryption' => $encryption,
            'mail.from.address' => $fromAddress ?: $username,
            'mail.from.name' => $fromName ?: config('app.name', 'ifotoset'),
        ]);
    }
}
