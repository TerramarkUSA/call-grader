<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use App\Models\User;
use App\Models\RubricCheckpoint;

// TODO: REMOVE AFTER USE - Temporary database fix route
Route::get('/fix-database-temp-123', function () {
    $output = [];

    // 1. Delete duplicate checkpoints - keep lowest ID for each unique name
    $checkpoints = RubricCheckpoint::orderBy('id')->get();
    $seen = [];
    $toDelete = [];

    foreach ($checkpoints as $checkpoint) {
        if (isset($seen[$checkpoint->name])) {
            $toDelete[] = $checkpoint->id;
        } else {
            $seen[$checkpoint->name] = $checkpoint->id;
        }
    }

    if (count($toDelete) > 0) {
        RubricCheckpoint::whereIn('id', $toDelete)->delete();
    }
    $output[] = "Deleted " . count($toDelete) . " duplicate checkpoints";

    // 2. Run the RubricSeeder to populate training_reference
    Artisan::call('db:seed', ['--class' => 'RubricSeeder', '--force' => true]);
    $output[] = "Seeder ran successfully";

    return response(implode("\n", $output), 200)->header('Content-Type', 'text/plain');
});

Route::get('/', function () {
    if (auth()->check()) {
        return redirect(match (auth()->user()->role) {
            'system_admin', 'site_admin' => '/admin/accounts',
            default => '/manager/calls',
        });
    }
    return redirect()->route('login');
});

// TODO: REMOVE BEFORE PRODUCTION - Dev login bypass
Route::get('/dev-login', function () {
    $user = User::where('role', 'system_admin')->first();
    if ($user) {
        auth()->login($user);
        return redirect('/manager/calls');
    }
    return 'No system admin found. Run: php artisan db:seed --class=SystemAdminSeeder';
});

require __DIR__.'/auth.php';
require __DIR__.'/admin.php';
require __DIR__.'/manager.php';
