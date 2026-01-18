<?php

use Illuminate\Support\Facades\Route;
use App\Models\User;

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
