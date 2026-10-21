<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Posts one Measurement Protocol payload to GA4. Queued so a slow Google
 * endpoint never delays sign-up, login or payment responses.
 */
class SendGoogleAnalyticsEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public array $backoff = [30, 120];

    public function __construct(
        public readonly array $payload
    ) {}

    public function handle(): void
    {
        $response = Http::timeout(5)
            ->withQueryParameters([
                'measurement_id' => config('services.google_analytics.id'),
                'api_secret' => config('services.google_analytics.api_secret'),
            ])
            ->post('https://www.google-analytics.com/mp/collect', $this->payload);

        // GA answers 2xx even for malformed events; only transport/5xx failures are worth retrying
        if ($response->serverError()) {
            $response->throw();
        }
    }

    public function failed(Throwable $e): void
    {
        Log::warning('Google Analytics event could not be sent: ' . $e->getMessage(), [
            'events' => array_column($this->payload['events'] ?? [], 'name'),
        ]);
    }
}
