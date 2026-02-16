<?php

use App\Jobs\RefreshCallOutcomes;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Sync calls from CTM every 15 minutes
Schedule::command('ctm:sync')->everyFifteenMinutes();

// Schedule Salesforce outcome refresh daily at 2am
Schedule::job(new RefreshCallOutcomes)->dailyAt('02:00');

// Daily sync health check at 7am
Schedule::command('sync:health-check')->dailyAt('07:00');

// Detect abandoned call interactions hourly
Schedule::command('call:detect-abandoned')->hourly();
