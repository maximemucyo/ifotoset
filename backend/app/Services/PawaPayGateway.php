<?php

namespace App\Services;

use App\Contracts\PaymentGateway;
use Exception;
use Illuminate\Support\Facades\Http;

class PawaPayGateway implements PaymentGateway
{
    protected string $apiUrl;
    protected string $apiKey;

    public function __construct()
    {
        $this->apiUrl = config('services.pawapay.url', 'https://api.pawapay.io');
        $this->apiKey = config('services.pawapay.api_key', 'test-api-key');
    }

    /**
     * Initiates mobile money payment deposit on PawaPay.
     */
    public function initiateDeposit(array $data): array
    {
        // Format payload to fit PawaPay API spec
        $payload = [
            'depositId' => $data['deposit_id'],
            'amount' => (string) $data['amount'],
            'currency' => $data['currency'],
            'country' => $data['country'] ?? 'RWA', // Default Rwanda
            'correspondent' => $data['provider'],  // MTN, AIRTEL
            'payer' => [
                'address' => [
                    'value' => $data['phone_number'],
                ],
            ],
            'description' => $data['description'] ?? 'ifotoset Plan Subscription',
        ];

        $response = Http::withToken($this->apiKey)
            ->timeout(15)
            ->post("{$this->apiUrl}/deposits", $payload);

        if (!$response->successful()) {
            $error = $response->json();
            throw new Exception($error['message'] ?? 'Failed to initiate PawaPay deposit request.');
        }

        return $response->json();
    }

    /**
     * Verifies deposit status directly from PawaPay.
     */
    public function verifyDepositStatus(string $depositId): array
    {
        $response = Http::withToken($this->apiKey)
            ->timeout(10)
            ->get("{$this->apiUrl}/deposits/{$depositId}");

        if (!$response->successful()) {
            throw new Exception("Unable to verify deposit status for ID: {$depositId}");
        }

        return $response->json();
    }
}
?>
