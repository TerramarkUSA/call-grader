<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect(match (auth()->user()->role) {
            'system_admin', 'site_admin' => '/admin/accounts',
            default => '/manager/calls',
        });
    }
    return redirect()->route('login');
});

require __DIR__.'/auth.php';
require __DIR__.'/admin.php';
require __DIR__.'/manager.php';
