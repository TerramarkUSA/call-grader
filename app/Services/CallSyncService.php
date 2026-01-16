<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Call;
use Illuminate\Support\Facades\Log;

class CallSyncService
{
    protected CTMService $ctmService;
    protected Account $account;

    public function __construct(Account $account)
    {
        $this->account = $account;
        $this->ctmService = new CTMService($account);
    }

    /**
     * Sync calls from CTM for the account
     */
    public function sync(int $days = 14): array
    {
        $stats = [
            'total_fetched' => 0,
            'created' => 0,
            'updated' => 0,
            'errors' => 0,
        ];

        $page = 1;
        $hasMore = true;

        while ($hasMore) {
            $result = $this->ctmService->fetchCalls([
                'start_date' => now()->subDays($days)->toDateString(),
                'end_date' => now()->toDateString(),
                'page' => $page,
                'per_page' => 100,
            ]);

            if (!$result['success']) {
                Log::error('Call sync failed', [
                    'account_id' => $this->account->id,
                    'page' => $page,
                    'message' => $result['message'] ?? 'Unknown error',
                ]);
                break;
            }

            foreach ($result['calls'] as $ctmCall) {
                try {
                    $syncResult = $this->syncCall($ctmCall);
                    $stats[$syncResult]++;
                    $stats['total_fetched']++;
                } catch (\Exception $e) {
                    Log::error('Error syncing call', [
                        'account_id' => $this->account->id,
                        'ctm_call_id' => $ctmCall['id'] ?? 'unknown',
                        'error' => $e->getMessage(),
                    ]);
                    $stats['errors']++;
                }
            }

            $hasMore = $page < ($result['total_pages'] ?? 1);
            $page++;

            // Safety limit
            if ($page > 50) {
                Log::warning('Call sync hit page limit', ['account_id' => $this->account->id]);
                break;
            }
        }

        // Update last sync timestamp
        $this->account->update(['last_sync_at' => now()]);

        return $stats;
    }

    /**
     * Sync a single call from CTM data
     */
    protected function syncCall(array $ctmCall): string
    {
        $ctmActivityId = $ctmCall['id'] ?? null;

        if (!$ctmActivityId) {
            throw new \Exception('Call missing ID');
        }

        // Map CTM data to our schema
        $callData = [
            'account_id' => $this->account->id,
            'ctm_activity_id' => $ctmActivityId,
            'caller_name' => $this->extractCallerName($ctmCall),
            'caller_number' => $ctmCall['caller_number'] ?? $ctmCall['calling_number'] ?? 'Unknown',
            'talk_time' => $this->extractTalkTime($ctmCall),
            'dial_status' => $this->mapDialStatus($ctmCall),
            'source' => $ctmCall['source'] ?? $ctmCall['tracking_source'] ?? null,
            'called_at' => $this->parseDateTime($ctmCall['called_at'] ?? $ctmCall['start_time'] ?? now()),
        ];

        // Check if call exists
        $existingCall = Call::where('ctm_activity_id', $ctmActivityId)->first();

        if ($existingCall) {
            // Only update if not already processed or ignored
            if (!$existingCall->processed_at && !$existingCall->ignored_at) {
                $existingCall->update($callData);
                return 'updated';
            }
            return 'updated'; // Count as updated even if skipped
        }

        // Create new call
        Call::create($callData);
        return 'created';
    }

    /**
     * Extract caller name from CTM data
     */
    protected function extractCallerName(array $ctmCall): ?string
    {
        return $ctmCall['caller_name']
            ?? $ctmCall['name']
            ?? $ctmCall['caller_id']
            ?? null;
    }

    /**
     * Extract talk time in seconds
     */
    protected function extractTalkTime(array $ctmCall): int
    {
        // CTM may return talk_time, duration, or talk_time_seconds
        if (isset($ctmCall['talk_time'])) {
            return $this->parseTimeToSeconds($ctmCall['talk_time']);
        }
        if (isset($ctmCall['talk_time_seconds'])) {
            return (int) $ctmCall['talk_time_seconds'];
        }
        if (isset($ctmCall['duration'])) {
            return $this->parseTimeToSeconds($ctmCall['duration']);
        }
        return 0;
    }

    /**
     * Parse time string (MM:SS or seconds) to seconds
     */
    protected function parseTimeToSeconds($time): int
    {
        if (is_numeric($time)) {
            return (int) $time;
        }

        if (is_string($time) && str_contains($time, ':')) {
            $parts = explode(':', $time);
            if (count($parts) === 2) {
                return ((int) $parts[0] * 60) + (int) $parts[1];
            }
            if (count($parts) === 3) {
                return ((int) $parts[0] * 3600) + ((int) $parts[1] * 60) + (int) $parts[2];
            }
        }

        return 0;
    }

    /**
     * Map CTM dial status to our status
     */
    protected function mapDialStatus(array $ctmCall): string
    {
        $status = strtolower($ctmCall['dial_status'] ?? $ctmCall['status'] ?? 'unknown');

        return match (true) {
            str_contains($status, 'answer') => 'answered',
            str_contains($status, 'busy') => 'busy',
            str_contains($status, 'no answer'), str_contains($status, 'no_answer') => 'no_answer',
            str_contains($status, 'voicemail') => 'voicemail',
            str_contains($status, 'cancel') => 'cancelled',
            default => $status,
        };
    }

    /**
     * Parse datetime from various formats
     */
    protected function parseDateTime($datetime): \Carbon\Carbon
    {
        if ($datetime instanceof \Carbon\Carbon) {
            return $datetime;
        }

        try {
            return \Carbon\Carbon::parse($datetime);
        } catch (\Exception $e) {
            return now();
        }
    }
}
