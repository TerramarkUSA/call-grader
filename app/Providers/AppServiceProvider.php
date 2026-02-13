<?php

namespace App\Providers;

use App\Models\Call;
use App\Policies\CallPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register authorization policies
        Gate::policy(Call::class, CallPolicy::class);

        // Configure rate limiters for sensitive endpoints
        $this->configureRateLimiting();

        // Alert on critical job failures
        $this->configureQueueFailureAlerts();
    }

    /**
     * Send email alerts when critical queue jobs fail.
     */
    protected function configureQueueFailureAlerts(): void
    {
        Queue::failing(function (JobFailed $event) {
            // Only alert for the daily Salesforce outcome refresh
            // EnrichCallFromSalesforce fails gracefully (no Chance found) — too noisy
            if ($event->job->resolveName() === 'App\\Jobs\\RefreshCallOutcomes') {
                $alertEmail = env('ALERT_EMAIL');
                if ($alertEmail) {
                    try {
                        Mail::raw(
                            "CRITICAL: Salesforce outcome refresh failed\n\n" .
                            "Error: {$event->exception->getMessage()}\n\n" .
                            "Time: " . now()->format('M j, Y g:i A') . "\n\n" .
                            "The daily Salesforce outcome sync did not complete.\n" .
                            "Check Laravel logs for details.",
                            fn($msg) => $msg->to($alertEmail)
                                            ->subject('⚠️ Call Grader: Salesforce Sync Failed')
                        );
                    } catch (\Exception $e) {
                        Log::error('Failed to send sync failure alert email', [
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
        });
    }

    /**
     * Configure rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        // Grading operations: 30 per minute per user
        RateLimiter::for('grading', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        // Transcription operations: 20 per minute per user (API calls are expensive)
        RateLimiter::for('transcription', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
        });

        // Admin actions: 10 per minute per user (user management, invites)
        RateLimiter::for('admin-actions', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });
    }
}
