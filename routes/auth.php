<?php

use App\Http\Controllers\Auth\MagicLinkController;
use App\Http\Controllers\Auth\PasswordLoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use Illuminate\Support\Facades\Route;

// Guest routes (not logged in)
Route::middleware('guest')->group(function () {
    // Password login (primary)
    Route::get('/login', [PasswordLoginController::class, 'showLogin'])->name('login');
    Route::post('/login', [PasswordLoginController::class, 'login'])->middleware('throttle:5,1');

    // Magic link login (fallback)
    Route::post('/login/magic-link', [MagicLinkController::class, 'sendLink'])->name('login.magic')->middleware('throttle:5,1');
    Route::get('/login/verify/{token}', [MagicLinkController::class, 'verify'])->name('login.verify');
    Route::post('/login/verify/{token}', [MagicLinkController::class, 'processVerify'])->name('login.verify.process');

    // Password reset
    Route::get('/forgot-password', [PasswordResetController::class, 'showForgotForm'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])->name('password.email')->middleware('throttle:5,1');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.update');

    // Set password (for new users after magic link invite)
    Route::get('/set-password', [PasswordResetController::class, 'showSetPasswordForm'])->name('password.set');
    Route::post('/set-password', [PasswordResetController::class, 'setPassword'])->name('password.store');
});

// Authenticated routes
Route::middleware('auth')->group(function () {
    Route::post('/logout', [PasswordLoginController::class, 'logout'])->name('logout');
});
