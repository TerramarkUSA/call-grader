<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Account;
use App\Models\Call;
use App\Models\Rep;
use App\Models\Project;
use Carbon\Carbon;

class TestCallsSeeder extends Seeder
{
    public function run(): void
    {
        // Get or create a test account
        $account = Account::first();

        if (!$account) {
            $account = Account::create([
                'name' => 'Test Office',
                'ctm_account_id' => 'TEST123',
                'ctm_api_key' => 'test_key',
                'ctm_api_secret' => 'test_secret',
                'is_active' => true,
            ]);
        }

        // Create some reps
        $reps = [];
        $repNames = ['Sarah Johnson', 'Mike Chen', 'Lisa Park', 'Tom Wilson'];
        foreach ($repNames as $name) {
            $reps[] = Rep::firstOrCreate(
                ['account_id' => $account->id, 'name' => $name],
                ['is_active' => true]
            );
        }

        // Create some projects
        $projects = [];
        $projectNames = ['Pine Ridge', 'Lake Haven', 'Sunset Shores', 'Mountain View'];
        foreach ($projectNames as $name) {
            $projects[] = Project::firstOrCreate(
                ['account_id' => $account->id, 'name' => $name],
                ['is_active' => true]
            );
        }

        // Create test calls
        $statuses = ['answered', 'answered', 'answered', 'no_answer', 'busy', 'voicemail'];
        $sources = ['Website', 'Direct', 'Google Ads', 'Facebook'];

        for ($i = 0; $i < 50; $i++) {
            $daysAgo = rand(0, 20);
            $talkTime = $statuses[array_rand($statuses)] === 'answered' ? rand(30, 900) : rand(0, 30);

            Call::create([
                'account_id' => $account->id,
                'ctm_activity_id' => 'TEST_' . uniqid(),
                'caller_name' => $this->randomName(),
                'caller_number' => $this->randomPhone(),
                'talk_time' => $talkTime,
                'dial_status' => $statuses[array_rand($statuses)],
                'source' => $sources[array_rand($sources)],
                'called_at' => Carbon::now()->subDays($daysAgo)->subHours(rand(0, 23))->subMinutes(rand(0, 59)),
                'rep_id' => $reps[array_rand($reps)]->id,
                'project_id' => $projects[array_rand($projects)]->id,
            ]);
        }
    }

    protected function randomName(): string
    {
        $firstNames = ['John', 'Jane', 'Bob', 'Alice', 'Charlie', 'Diana', 'Edward', 'Fiona'];
        $lastNames = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis'];
        return $firstNames[array_rand($firstNames)] . ' ' . $lastNames[array_rand($lastNames)];
    }

    protected function randomPhone(): string
    {
        return sprintf('(%03d) %03d-%04d', rand(200, 999), rand(200, 999), rand(1000, 9999));
    }
}
