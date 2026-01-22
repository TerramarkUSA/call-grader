<?php

namespace App\Jobs;

use App\Models\Call;
use App\Services\SalesforceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class EnrichCallFromSalesforce implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [900, 900, 1800]; // 15min, 15min, 30min

    public function __construct(
        public Call $call
    ) {}

    public function handle(): void
    {
        $service = new SalesforceService();

        if (!$service->isConnected()) {
            Log::info('Salesforce not connected, skipping enrichment', ['call_id' => $this->call->id]);
            return;
        }

        $success = $service->enrichCall($this->call);

        if (!$success) {
            $this->call->increment('sf_sync_attempts');

            if ($this->attempts() < $this->tries) {
                Log::info('Salesforce Chance not found, will retry', [
                    'call_id' => $this->call->id,
                    'attempt' => $this->attempts()
                ]);
                throw new \Exception('Chance not found in Salesforce, will retry');
            }

            Log::warning('Salesforce enrichment failed after all retries', ['call_id' => $this->call->id]);
        }
    }
}
