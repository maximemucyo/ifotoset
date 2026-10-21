<?php

namespace App\Support;

use App\Jobs\SendGoogleAnalyticsEvent;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Server-side GA4 events via the Measurement Protocol.
 *
 * The gtag.js tag only runs on marketing pages, so conversions (sign-up, login,
 * purchase) are sent from the server instead, keyed to the browser's _ga client id
 * so GA attributes them to the visit that brought the user in.
 */
final class GoogleAnalytics
{
    public static function enabled(): bool
    {
        return filled(config('services.google_analytics.id'))
            && filled(config('services.google_analytics.api_secret'));
    }

    /**
     * Track an event for the current browser request.
     */
    public static function track(Request $request, User $user, string $event, array $params = []): void
    {
        self::send($user, $event, $params, self::clientId($request), self::sessionId($request));
    }

    /**
     * Track an event outside a browser request (webhooks, queued work).
     * Pass the client id captured earlier, or null to fall back to one derived from the user.
     */
    public static function send(User $user, string $event, array $params = [], ?string $clientId = null, ?string $sessionId = null): void
    {
        if (!self::enabled()) {
            return;
        }

        if ($sessionId) {
            // GA only attributes an event to a session when both of these are present
            $params += ['session_id' => $sessionId, 'engagement_time_msec' => 1];
        }

        SendGoogleAnalyticsEvent::dispatch([
            'client_id' => $clientId ?: self::fallbackClientId($user),
            'user_id' => (string) $user->uuid,
            'events' => [['name' => $event, 'params' => $params]],
        ]);
    }

    /**
     * Client id from the _ga cookie ("GA1.1.123456789.1700000000" -> "123456789.1700000000").
     */
    public static function clientId(Request $request): ?string
    {
        $cookie = (string) $request->cookie('_ga', '');

        return preg_match('/^GA\d\.\d+\.(\d+\.\d+)$/', $cookie, $m) ? $m[1] : null;
    }

    /**
     * Session id from the _ga_<container> cookie, in either the GS1 or GS2 format.
     */
    public static function sessionId(Request $request): ?string
    {
        $cookie = (string) $request->cookie(self::sessionCookieName(), '');

        if (preg_match('/^GS1\.\d+\.(\d+)\./', $cookie, $m) || preg_match('/^GS2\.\d+\.s(\d+)/', $cookie, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * Cookies gtag.js writes; they must reach PHP unencrypted.
     *
     * @return list<string>
     */
    public static function cookieNames(): array
    {
        return ['_ga', self::sessionCookieName()];
    }

    private static function sessionCookieName(): string
    {
        return '_ga_' . preg_replace('/^G-/', '', (string) config('services.google_analytics.id'));
    }

    /**
     * Stable per-user id for users who never loaded the tag (or blocked it).
     */
    private static function fallbackClientId(User $user): string
    {
        return sprintf('%u.%d', crc32((string) $user->uuid), $user->created_at?->timestamp ?? 0);
    }
}
