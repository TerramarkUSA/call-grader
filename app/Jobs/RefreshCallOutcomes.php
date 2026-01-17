<?php

namespace App\Jobs;

use App\Models\Account;
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
        $accounts = Account::whereNotNull('sf_connected_at')->get();

        foreach ($accounts as $account) {
            $service = new SalesforceService($account);

            $calls = Call::where('account_id', $account->id)
                ->whereNotNull('sf_chance_id')
                ->where('called_at', '>=', now()->subDays(90))
                ->get();

            $updated = 0;
            foreach ($calls as $call) {
                if ($service->refreshOutcomes($call)) {
                    $updated++;
                }
            }

            Log::info('Refreshed Salesforce outcomes', [
                'account' => $account->name,
                'calls_updated' => $updated
            ]);
        }
    }
}
