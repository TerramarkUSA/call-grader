<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Services\CallSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncCtmCalls extends Command
{
    protected $signature = 'ctm:sync {--days=14 : Number of days to sync}';

    protected $description = 'Sync calls from CTM for all active accounts';

    public function handle(): int
    {
        $accounts = Account::where('is_active', true)
            ->whereNotNull('ctm_api_key')
            ->whereNotNull('ctm_api_secret')
            ->whereNotNull('ctm_account_id')
            ->get();

        if ($accounts->isEmpty()) {
            $this->info('No active accounts with CTM credentials found.');
            return Command::SUCCESS;
        }

        $days = (int) $this->option('days');
        $totalCreated = 0;
        $totalUpdated = 0;

        $this->info("Syncing calls from {$accounts->count()} account(s)...");

        foreach ($accounts as $account) {
            $this->line("  Syncing: {$account->name}");

            try {
                $syncService = new CallSyncService($account);
                $stats = $syncService->sync($days);

                $totalCreated += $stats['created'];
                $totalUpdated += $stats['updated'];

                $this->line("    Created: {$stats['created']}, Updated: {$stats['updated']}, Errors: {$stats['errors']}");

                Log::info('CTM sync completed for account', [
                    'account_id' => $account->id,
                    'account_name' => $account->name,
                    'created' => $stats['created'],
                    'updated' => $stats['updated'],
                    'errors' => $stats['errors'],
                ]);

            } catch (\Exception $e) {
                $this->error("    Failed: {$e->getMessage()}");

                Log::error('CTM sync failed for account', [
                    'account_id' => $account->id,
                    'account_name' => $account->name,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->newLine();
        $this->info("Sync complete. Total: {$totalCreated} created, {$totalUpdated} updated.");

        return Command::SUCCESS;
    }
}
