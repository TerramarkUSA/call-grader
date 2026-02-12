<?php

namespace App\Jobs;

use App\Models\Call;
use App\Services\SalesforceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class EnrichCallFromSalesforce implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [900, 1800, 3600]; // 15min, 30min, 1hr

    public function __construct(
        public Call $call
    ) {}

    public function handle(): void
    {
        $service = new SalesforceService();

        // Clear cache to get latest connection status
        Cache::forget('setting.sf_connected_at');
        Cache::forget('setting.sf_refresh_token');

        if (!$service->isConnected()) {
            Log::info('Salesforce not connected, skipping enrichment', ['call_id' => $this->call->id]);
            return; // Completes job without error
        }

        try {
            $success = $service->enrichCall($this->call);
        } catch (\Exception $e) {
            // Actual API error (timeout, auth failure, etc.) — should retry
            Log::error('Salesforce enrichment API error', [
                'call_id' => $this->call->id,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);
            throw $e; // Re-throw so the queue retries
        }

        if ($success) {
            Log::info('Salesforce enrichment successful', ['call_id' => $this->call->id]);
            return;
        }

        // No Chance found — expected for many calls
        $this->call->increment('sf_sync_attempts');

        if ($this->attempts() < $this->tries) {
            // Still have retries left — Chance might not exist yet
            $delay = $this->backoff[$this->attempts() - 1] ?? 3600;
            Log::info('No Salesforce Chance found yet, will retry', [
                'call_id' => $this->call->id,
                'ctm_activity_id' => $this->call->ctm_activity_id,
                'attempt' => $this->attempts(),
                'next_retry_seconds' => $delay,
            ]);
            $this->release($delay);
            return;
        }

        // Final attempt — no Chance exists. That's OK, just log and complete.
        Log::info('No Salesforce Chance found after all retries — call will remain unenriched', [
            'call_id' => $this->call->id,
            'ctm_activity_id' => $this->call->ctm_activity_id,
        ]);
        // Job completes normally — does NOT go to failed_jobs
    }
}
