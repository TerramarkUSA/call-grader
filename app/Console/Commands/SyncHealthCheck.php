<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Setting;
use App\Services\SalesforceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SyncHealthCheck extends Command
{
    protected $signature = 'sync:health-check';

    protected $description = 'Check sync health and email alert if issues found';

    public function handle(): int
    {
        $issues = [];

        // 1. Check failed_jobs in last 24 hours
        $issues = array_merge($issues, $this->checkFailedJobs());

        // 2. Check CTM sync freshness per account
        $issues = array_merge($issues, $this->checkCtmSync());

        // 3. Check Salesforce connection and sync freshness
        $issues = array_merge($issues, $this->checkSalesforceSync());

        if (empty($issues)) {
            Log::info('Sync health check passed — no issues found.');
            $this->info('Health check passed. No issues found.');
            return Command::SUCCESS;
        }

        // Build and send alert email
        $this->sendAlert($issues);

        $this->warn(count($issues) . ' issue(s) found. Alert email sent.');
        return Command::SUCCESS;
    }

    /**
     * Check for any failed jobs in the last 24 hours.
     */
    protected function checkFailedJobs(): array
    {
        $issues = [];

        $failedJobs = DB::table('failed_jobs')
            ->where('failed_at', '>=', now()->subDay())
            ->get(['payload', 'exception', 'failed_at']);

        if ($failedJobs->count() > 0) {
            $jobNames = $failedJobs->map(function ($job) {
                $payload = json_decode($job->payload, true);
                $name = $payload['displayName'] ?? 'Unknown';
                $time = $job->failed_at;
                return "  - {$name} (failed at {$time})";
            })->implode("\n");

            $issues[] = "FAILED JOBS ({$failedJobs->count()} in last 24h):\n{$jobNames}";
        }

        return $issues;
    }

    /**
     * Check CTM sync freshness for each active account.
     */
    protected function checkCtmSync(): array
    {
        $issues = [];

        $accounts = Account::where('is_active', true)
            ->whereNotNull('ctm_api_key')
            ->get(['id', 'name', 'last_sync_at']);

        foreach ($accounts as $account) {
            if ($account->last_sync_at === null) {
                $issues[] = "CTM SYNC: \"{$account->name}\" has never synced.";
            } elseif ($account->last_sync_at->lt(now()->subDay())) {
                $ago = $account->last_sync_at->diffForHumans();
                $issues[] = "CTM SYNC: \"{$account->name}\" last synced {$ago} (over 24h).";
            }
        }

        return $issues;
    }

    /**
     * Check Salesforce connection status and sync freshness.
     */
    protected function checkSalesforceSync(): array
    {
        $issues = [];

        $service = new SalesforceService();

        if (!$service->isConnected()) {
            // Only flag if SF was previously configured (has a client ID)
            $sfClientId = Setting::get('sf_client_id');
            if ($sfClientId) {
                $issues[] = "SALESFORCE: Connection is disconnected. Token may have expired or been revoked.";
            }
            return $issues;
        }

        // Check last SF sync time
        $sfLastSync = Setting::get('sf_last_sync_at');
        if ($sfLastSync) {
            $lastSync = \Carbon\Carbon::parse($sfLastSync);
            if ($lastSync->lt(now()->subHours(48))) {
                $ago = $lastSync->diffForHumans();
                $issues[] = "SALESFORCE: Last sync was {$ago} (over 48h). The daily refresh may not be running.";
            }
        } elseif (Setting::get('sf_connected_at')) {
            $issues[] = "SALESFORCE: Connected but has never completed a sync.";
        }

        return $issues;
    }

    /**
     * Send a single summary email with all issues found.
     */
    protected function sendAlert(array $issues): void
    {
        $alertEmail = env('ALERT_EMAIL');

        if (!$alertEmail) {
            Log::warning('Sync health check found issues but ALERT_EMAIL is not set.', [
                'issue_count' => count($issues),
            ]);
            $this->error('ALERT_EMAIL not set — cannot send alert.');
            return;
        }

        $count = count($issues);
        $issueList = implode("\n\n", $issues);

        $body = "Call Grader Health Check — {$count} issue(s) found\n" .
                "Checked at: " . now()->format('M j, Y g:i A') . "\n" .
                str_repeat('─', 50) . "\n\n" .
                $issueList . "\n\n" .
                str_repeat('─', 50) . "\n" .
                "Review logs on the server for more details.\n" .
                "Run 'php artisan sync:health-check' manually to re-check.";

        try {
            Mail::raw($body, function ($msg) use ($alertEmail, $count) {
                $msg->to($alertEmail)
                    ->subject("Call Grader: Daily Health Report — {$count} issue(s) found");
            });

            Log::info('Sync health check alert sent', [
                'to' => $alertEmail,
                'issues' => $count,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send sync health check email', [
                'error' => $e->getMessage(),
            ]);
            $this->error("Failed to send alert email: {$e->getMessage()}");
        }
    }
}
