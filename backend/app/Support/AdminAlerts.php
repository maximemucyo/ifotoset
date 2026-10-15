<?php

namespace App\Support;

use App\Mail\AdminAlertMail;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sends operational alerts to ADMIN_ALERT_EMAILS (or every active admin).
 */
final class AdminAlerts
{
    /**
     * @return list<string>
     */
    public static function recipients(): array
    {
        $configured = config('mail.admin_alerts', []);
        if (!empty($configured)) {
            return $configured;
        }

        return User::where('role', 'admin')->where('is_active', true)->pluck('email')->filter()->values()->all();
    }

    /**
     * @param string|null $dedupeKey when set, the same alert is sent at most once per $dedupeMinutes
     */
    public static function send(AdminAlertMail $mail, ?string $dedupeKey = null, int $dedupeMinutes = 60): void
    {
        if ($dedupeKey && !Cache::add("admin-alert:{$dedupeKey}", true, now()->addMinutes($dedupeMinutes))) {
            return;
        }

        $recipients = self::recipients();
        if (empty($recipients)) {
            return;
        }

        try {
            Mail::to($recipients)->queue($mail);
        } catch (\Throwable $e) {
            Log::error('Admin alert could not be queued: ' . $e->getMessage());
        }
    }
}
