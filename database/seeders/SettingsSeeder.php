<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Setting;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            'grading_quality_flag_threshold' => '25',
            'grading_quality_suspicious_threshold' => '50',
            'cost_alert_daily_threshold' => '50',
            'cost_alert_weekly_threshold' => '200',
            'call_queue_active_days' => '14',
            'call_retention_days' => '90',
        ];

        foreach ($settings as $key => $value) {
            Setting::set($key, $value);
        }
    }
}
