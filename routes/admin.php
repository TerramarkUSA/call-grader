<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\RubricController;
use App\Http\Controllers\Admin\ObjectionTypeController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SalesforceController;
use App\Http\Controllers\Admin\CostDashboardController;
use App\Http\Controllers\Admin\QualityDashboardController;
use App\Http\Controllers\Admin\LeaderboardController;
use App\Http\Controllers\Admin\RepController;
use App\Http\Controllers\Admin\ProjectController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:system_admin,site_admin'])->prefix('admin')->name('admin.')->group(function () {

    // Dashboard redirect
    Route::get('/dashboard', function () {
        return redirect()->route('admin.accounts.index');
    })->name('dashboard');

    // Account (Office) Management
    Route::get('/accounts', [AccountController::class, 'index'])->name('accounts.index');
    Route::get('/accounts/create', [AccountController::class, 'create'])->name('accounts.create');
    Route::post('/accounts', [AccountController::class, 'store'])->name('accounts.store');
    Route::get('/accounts/{account}/edit', [AccountController::class, 'edit'])->name('accounts.edit');
    Route::put('/accounts/{account}', [AccountController::class, 'update'])->name('accounts.update');
    Route::post('/accounts/{account}/test-connection', [AccountController::class, 'testConnection'])->name('accounts.test-connection');
    Route::post('/accounts/{account}/sync-calls', [AccountController::class, 'syncCalls'])->name('accounts.sync-calls');
    Route::post('/accounts/{account}/toggle-active', [AccountController::class, 'toggleActive'])->name('accounts.toggle-active');

    // User Management
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::post('/users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle-active');
    Route::post('/users/{user}/resend-invite', [UserController::class, 'resendInvite'])->name('users.resend-invite');
    Route::put('/users/{user}/accounts', [UserController::class, 'updateAccounts'])->name('users.update-accounts');

    // Reps Management
    Route::get('/reps', [RepController::class, 'index'])->name('reps.index');
    Route::post('/reps', [RepController::class, 'store'])->name('reps.store');
    Route::patch('/reps/{rep}', [RepController::class, 'update'])->name('reps.update');
    Route::delete('/reps/{rep}', [RepController::class, 'destroy'])->name('reps.destroy');

    // Projects Management
    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::patch('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');

    // Rubric Management
    Route::get('/rubric/categories', [RubricController::class, 'categories'])->name('rubric.categories');
    Route::patch('/rubric/categories/{category}', [RubricController::class, 'updateCategory'])->name('rubric.categories.update');
    Route::post('/rubric/categories/reorder', [RubricController::class, 'reorderCategories'])->name('rubric.categories.reorder');
    Route::get('/rubric/checkpoints', [RubricController::class, 'checkpoints'])->name('rubric.checkpoints');
    Route::post('/rubric/checkpoints', [RubricController::class, 'storeCheckpoint'])->name('rubric.checkpoints.store');
    Route::patch('/rubric/checkpoints/{checkpoint}', [RubricController::class, 'updateCheckpoint'])->name('rubric.checkpoints.update');
    Route::delete('/rubric/checkpoints/{checkpoint}', [RubricController::class, 'deleteCheckpoint'])->name('rubric.checkpoints.destroy');
    Route::post('/rubric/checkpoints/reorder', [RubricController::class, 'reorderCheckpoints'])->name('rubric.checkpoints.reorder');

    // Objection Types Management
    Route::get('/objection-types', [ObjectionTypeController::class, 'index'])->name('objection-types.index');
    Route::post('/objection-types', [ObjectionTypeController::class, 'store'])->name('objection-types.store');
    Route::patch('/objection-types/{objectionType}', [ObjectionTypeController::class, 'update'])->name('objection-types.update');
    Route::delete('/objection-types/{objectionType}', [ObjectionTypeController::class, 'destroy'])->name('objection-types.destroy');
    Route::post('/objection-types/reorder', [ObjectionTypeController::class, 'reorder'])->name('objection-types.reorder');

    // Cost Dashboard
    Route::get('/costs', [CostDashboardController::class, 'index'])->name('costs.index');

    // Quality Dashboard
    Route::get('/quality', [QualityDashboardController::class, 'index'])->name('quality.index');
    Route::get('/quality/manager/{manager}', [QualityDashboardController::class, 'managerDetail'])->name('quality.manager');
    Route::get('/quality/audit', [QualityDashboardController::class, 'gradeAudit'])->name('quality.audit');

    // Leaderboard
    Route::get('/leaderboard', [LeaderboardController::class, 'index'])->name('leaderboard.index');

    // Settings (System Admin only)
    Route::middleware(['role:system_admin'])->group(function () {
        Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::post('/settings/api', [SettingsController::class, 'updateApi'])->name('settings.update-api');
        Route::post('/settings/grading', [SettingsController::class, 'updateGrading'])->name('settings.update-grading');
        Route::post('/settings/alerts', [SettingsController::class, 'updateAlerts'])->name('settings.update-alerts');
        Route::post('/settings/deepgram', [SettingsController::class, 'updateDeepgram'])->name('settings.update-deepgram');
        Route::post('/settings/test-deepgram', [SettingsController::class, 'testDeepgram'])->name('settings.test-deepgram');
        Route::post('/settings/test-email', [SettingsController::class, 'testEmail'])->name('settings.test-email');
        Route::get('/settings/mail-config', [SettingsController::class, 'mailConfig'])->name('settings.mail-config');

        // Salesforce routes
        Route::prefix('salesforce')->name('salesforce.')->group(function () {
            Route::get('/', [SalesforceController::class, 'index'])->name('index');
            Route::post('/{account}/credentials', [SalesforceController::class, 'saveCredentials'])->name('credentials');
            Route::get('/{account}/connect', [SalesforceController::class, 'connect'])->name('connect');
            Route::get('/callback', [SalesforceController::class, 'callback'])->name('callback');
            Route::post('/{account}/disconnect', [SalesforceController::class, 'disconnect'])->name('disconnect');
            Route::post('/{account}/test', [SalesforceController::class, 'testConnection'])->name('test');
            Route::post('/{account}/field-mapping', [SalesforceController::class, 'saveFieldMapping'])->name('field-mapping');
            Route::get('/{account}/users', [SalesforceController::class, 'getUsers'])->name('users');
            Route::post('/{account}/auto-match-reps', [SalesforceController::class, 'autoMatchReps'])->name('auto-match-reps');
            Route::post('/rep-mapping', [SalesforceController::class, 'saveRepMapping'])->name('rep-mapping');
            Route::post('/project-mapping', [SalesforceController::class, 'saveProjectMapping'])->name('project-mapping');
        });
    });

});
