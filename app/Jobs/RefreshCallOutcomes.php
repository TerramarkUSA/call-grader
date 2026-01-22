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

class RefreshCallOutcomes implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $service = new SalesforceService();

        if (!$service->isConnected()) {
            Log::info('Salesforce not connected, skipping outcome refresh');
            return;
        }

        $calls = Call::whereNotNull('sf_chance_id')
            ->where('called_at', '>=', now()->subDays(90))
            ->get();

        $updated = 0;
        foreach ($calls as $call) {
            if ($service->refreshOutcomes($call)) {
                $updated++;
            }
        }

        Log::info('Refreshed Salesforce outcomes', [
            'calls_updated' => $updated
        ]);
    }
}
