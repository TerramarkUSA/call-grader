<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\RubricCategory;
use App\Models\RubricCheckpoint;

// TODO: REMOVE AFTER USE - Temporary database fix route
Route::get('/fix-database-temp-123', function () {
    // 1. Disable foreign key checks and truncate tables
    DB::statement('SET FOREIGN_KEY_CHECKS=0;');
    RubricCategory::truncate();
    RubricCheckpoint::truncate();
    DB::statement('SET FOREIGN_KEY_CHECKS=1;');

    // 2. Run seeder
    Artisan::call('db:seed', ['--class' => 'RubricSeeder', '--force' => true]);

    // 3. Get counts
    $categories = RubricCategory::count();
    $positive = RubricCheckpoint::where('type', 'positive')->count();
    $negative = RubricCheckpoint::where('type', 'negative')->count();

    return response("Categories: {$categories}, Positive checkpoints: {$positive}, Negative checkpoints: {$negative}", 200)
        ->header('Content-Type', 'text/plain');
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
