<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class MailConfigServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Only configure if the settings table exists (after migration)
        if (!Schema::hasTable('settings')) {
            Log::debug('MailConfigServiceProvider: settings table does not exist yet');
            return;
        }

        try {
            $sendgridApiKey = Setting::getEncrypted('sendgrid_api_key');
            $fromEmail = Setting::get('sendgrid_from_email');
            $fromName = Setting::get('sendgrid_from_name', 'Call Grader');

            if ($sendgridApiKey) {
                // Configure SendGrid mailer with database credentials
                Config::set('mail.mailers.sendgrid.password', $sendgridApiKey);
                Config::set('mail.default', 'sendgrid');

                if ($fromEmail) {
                    Config::set('mail.from.address', $fromEmail);
                    Config::set('mail.from.name', $fromName);
                }

                Log::debug('MailConfigServiceProvider: SendGrid configured', [
                    'mailer' => 'sendgrid',
                    'from_email' => $fromEmail,
                    'from_name' => $fromName,
                    'api_key_set' => !empty($sendgridApiKey),
                ]);
            } else {
                Log::debug('MailConfigServiceProvider: No SendGrid API key configured, using default mailer');
            }
        } catch (\Exception $e) {
            Log::warning('MailConfigServiceProvider: Failed to configure mail', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
