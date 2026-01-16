<?php

use App\Http\Controllers\Auth\MagicLinkController;
use Illuminate\Support\Facades\Route;

// Public auth routes
Route::middleware('guest')->group(function () {
    Route::get('/login', [MagicLinkController::class, 'showLogin'])->name('login');
    Route::post('/login', [MagicLinkController::class, 'sendLink'])->name('login.send');
    Route::get('/login/verify/{token}', [MagicLinkController::class, 'verify'])->name('login.verify');
});

// Authenticated routes
Route::middleware('auth')->group(function () {
    Route::post('/logout', [MagicLinkController::class, 'logout'])->name('logout');
});
