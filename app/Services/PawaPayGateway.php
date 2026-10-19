<?php

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\DTO\PaymentStatusData;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PawaPayGateway implements PaymentGateway
{
    protected string $apiUrl;
    protected string $apiKey;
    protected string $defaultCurrency;
    protected string $defaultCountry;

    public function __construct()
    {
        $this->apiUrl = rtrim(config('services.pawapay.url', 'https://api.pawapay.io/v2'), '/');
        $this->apiKey = (string) config('services.pawapay.api_key', '');
        $this->defaultCurrency = (string) config('services.pawapay.currency', 'RWF');
        $this->defaultCountry = (string) config('services.pawapay.country', 'RWA');
    }

    /**
     * Clean and normalize a phone number for Rwanda.
     */
    public function normalizePhoneNumber(string $phone): string
    {
        $clean = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($clean) === 10 && str_starts_with($clean, '07')) {
            return '250' . substr($clean, 1);
        } elseif (strlen($clean) === 9 && str_starts_with($clean, '7')) {
            return '250' . $clean;
        }
        return $clean;
    }

    /**
     * Predict the telecom provider for a phone number using PawaPay V2.
     *
     * @return array{phoneNumber: string, provider: string, country: string}
     * @throws Exception
     */
    public function predictProvider(string $phone): array
    {
        $cleanPhone = $this->normalizePhoneNumber($phone);

        $response = Http::withToken($this->apiKey)
            ->timeout(10)
            ->post("{$this->apiUrl}/predict-provider", [
                'phoneNumber' => $cleanPhone,
            ]);

        if (!$response->successful()) {
            Log::warning("PawaPay predict-provider failure: " . $response->body());
            // Fallback heuristics for Rwanda if API is temporarily degraded
            if (str_starts_with($cleanPhone, '25078') || str_starts_with($cleanPhone, '25079')) {
                return ['phoneNumber' => $cleanPhone, 'provider' => 'MTN_MOMO_RWA', 'country' => 'RWA'];
            } elseif (str_starts_with($cleanPhone, '25072') || str_starts_with($cleanPhone, '25073')) {
                return ['phoneNumber' => $cleanPhone, 'provider' => 'AIRTEL_RWA', 'country' => 'RWA'];
            }
            throw new Exception("Unable to determine telecom provider for phone number: {$phone}");
        }

        $data = $response->json();
        if (empty($data['provider'])) {
            throw new Exception("Invalid phone number or unsupported telecom provider.");
        }

        return [
            'phoneNumber' => $data['phoneNumber'] ?? $cleanPhone,
            'provider' => $data['provider'],
            'country' => $data['country'] ?? 'RWA',
        ];
    }

    /**
     * Initiates mobile money payment deposit on PawaPay V2.
     *
     * @param array{deposit_id: string, amount: numeric, currency?: string, phone_number: string, provider?: string} $data
     * @throws Exception
     */
    public function initiateDeposit(array $data): array
    {
        $phone = (string) $data['phone_number'];
        $provider = $data['provider'] ?? null;

        // Predict provider if not explicitly specified
        if (empty($provider)) {
            $prediction = $this->predictProvider($phone);
            $phone = $prediction['phoneNumber'];
            $provider = $prediction['provider'];
        } else {
            $phone = $this->normalizePhoneNumber($phone);
        }

        $depositId = (string) $data['deposit_id'];
        $amount = (string) $data['amount'];
        $currency = $data['currency'] ?? $this->defaultCurrency;

        // Standardized PawaPay V2 MMO Payload
        $payload = [
            'depositId' => $depositId,
            'amount' => $amount,
            'currency' => $currency,
            'payer' => [
                'type' => 'MMO',
                'accountDetails' => [
                    'phoneNumber' => $phone,
                    'provider' => $provider,
                ],
            ],
        ];

        $response = Http::withToken($this->apiKey)
            ->timeout(15)
            ->post("{$this->apiUrl}/deposits", $payload);

        if (!$response->successful()) {
            $error = $response->json();
            $msg = $error['failureReason']['failureMessage'] 
                ?? $error['message'] 
                ?? 'Failed to initiate PawaPay deposit request.';
            Log::error("PawaPay V2 deposit initiation failed: " . $response->body(), ['payload' => $payload]);
            throw new Exception($msg);
        }

        return $response->json() ?? [];
    }

    /**
     * Verifies deposit status directly from PawaPay V2 API.
     */
    public function verifyDepositStatus(string $depositId): PaymentStatusData
    {
        $response = Http::withToken($this->apiKey)
            ->timeout(10)
            ->get("{$this->apiUrl}/deposits/{$depositId}");

        if (!$response->successful()) {
            Log::warning("PawaPay V2 status verification failed for deposit: {$depositId}, HTTP {$response->status()}");
            return new PaymentStatusData(
                found: false,
                status: 'UNKNOWN',
                depositId: $depositId,
                amount: 0.0,
                currency: $this->defaultCurrency,
                failureReason: 'PawaPay API query error: ' . $response->status()
            );
        }

        $data = $response->json() ?? [];
        $isFound = isset($data['status']) && $data['status'] === 'FOUND';

        if (!$isFound || !isset($data['data'])) {
            return new PaymentStatusData(
                found: false,
                status: 'NOT_FOUND',
                depositId: $depositId,
                amount: 0.0,
                currency: $this->defaultCurrency,
                failureReason: 'Deposit record not found on PawaPay'
            );
        }

        $depositData = $data['data'];
        $status = strtoupper($depositData['status'] ?? 'UNKNOWN');
        $amount = (float) ($depositData['amount'] ?? 0.0);
        $currency = (string) ($depositData['currency'] ?? $this->defaultCurrency);
        $providerTxId = $depositData['providerTransactionId'] ?? null;
        $failureReason = null;

        if (isset($depositData['failureReason'])) {
            $failureReason = is_array($depositData['failureReason'])
                ? ($depositData['failureReason']['failureMessage'] ?? json_encode($depositData['failureReason']))
                : (string) $depositData['failureReason'];
        }

        return new PaymentStatusData(
            found: true,
            status: $status,
            depositId: $depositId,
            amount: $amount,
            currency: $currency,
            provider: $depositData['provider'] ?? null,
            providerTransactionId: $providerTxId,
            failureReason: $failureReason,
            raw: $data
        );
    }
}
