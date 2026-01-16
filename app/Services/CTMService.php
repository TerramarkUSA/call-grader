<?php

namespace App\Services;

use App\Models\Account;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class CTMService
{
    protected Account $account;
    protected string $baseUrl;

    public function __construct(Account $account)
    {
        $this->account = $account;
        $this->baseUrl = config('services.ctm.base_url', 'https://api.calltrackingmetrics.com/api/v1');
    }

    /**
     * Test the CTM connection
     */
    public function testConnection(): array
    {
        try {
            $response = $this->request('GET', "/accounts/{$this->account->ctm_account_id}.json");

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Connection successful',
                    'account_name' => $response->json('name'),
                ];
            }

            return [
                'success' => false,
                'message' => 'Invalid credentials or account ID',
            ];
        } catch (Exception $e) {
            Log::error('CTM connection test failed', [
                'account_id' => $this->account->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Fetch calls from CTM
     */
    public function fetchCalls(array $params = []): array
    {
        $defaultParams = [
            'start_date' => now()->subDays(14)->toDateString(),
            'end_date' => now()->toDateString(),
            'per_page' => 100,
            'page' => 1,
        ];

        $params = array_merge($defaultParams, $params);

        try {
            $response = $this->request('GET', "/accounts/{$this->account->ctm_account_id}/calls.json", $params);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'calls' => $response->json('calls', []),
                    'total_entries' => $response->json('total_entries', 0),
                    'total_pages' => $response->json('total_pages', 1),
                    'page' => $response->json('page', 1),
                ];
            }

            return [
                'success' => false,
                'message' => 'Failed to fetch calls',
                'calls' => [],
            ];
        } catch (Exception $e) {
            Log::error('CTM fetch calls failed', [
                'account_id' => $this->account->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Error fetching calls: ' . $e->getMessage(),
                'calls' => [],
            ];
        }
    }

    /**
     * Fetch a single call by ID
     */
    public function fetchCall(string $callId): array
    {
        try {
            $response = $this->request('GET', "/accounts/{$this->account->ctm_account_id}/calls/{$callId}.json");

            if ($response->successful()) {
                return [
                    'success' => true,
                    'call' => $response->json(),
                ];
            }

            return [
                'success' => false,
                'message' => 'Call not found',
            ];
        } catch (Exception $e) {
            Log::error('CTM fetch call failed', [
                'account_id' => $this->account->id,
                'call_id' => $callId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Error fetching call: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get the recording URL for a call
     */
    public function getRecordingUrl(string $callId): ?string
    {
        $result = $this->fetchCall($callId);

        if ($result['success'] && isset($result['call']['audio'])) {
            return $result['call']['audio'];
        }

        return null;
    }

    /**
     * Make an authenticated request to CTM API
     */
    protected function request(string $method, string $endpoint, array $params = [])
    {
        $url = $this->baseUrl . $endpoint;

        return Http::withBasicAuth(
            $this->account->ctm_api_key,
            $this->account->ctm_api_secret
        )
        ->timeout(30)
        ->$method($url, $params);
    }
}
