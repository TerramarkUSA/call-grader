<?php

use App\Http\Controllers\Manager\CallAnalyticsController;
use App\Http\Controllers\Manager\CallQueueController;
use App\Http\Controllers\Manager\CoachingNoteController;
use App\Http\Controllers\Manager\DashboardController;
use App\Http\Controllers\Manager\GoldenMomentsController;
use App\Http\Controllers\Manager\UpdatesController;
use App\Http\Controllers\Manager\GradedCallsController;
use App\Http\Controllers\Manager\GradingController;
use App\Http\Controllers\Manager\NotesLibraryController;
use App\Http\Controllers\Manager\ObjectionsLibraryController;
use App\Http\Controllers\Manager\PerformanceController;
use App\Http\Controllers\Manager\ReportsController;
use App\Http\Controllers\Manager\TranscriptionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:system_admin,site_admin,manager', 'has.account'])->prefix('manager')->name('manager.')->group(function () {

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/feedback', [DashboardController::class, 'submitFeedback'])->name('feedback');

    // Reports
    Route::get('/reports/rep-performance', [ReportsController::class, 'repPerformance'])->name('reports.rep-performance');
    Route::get('/reports/category-breakdown', [ReportsController::class, 'categoryBreakdown'])->name('reports.category-breakdown');
    Route::get('/reports/objection-analysis', [ReportsController::class, 'objectionAnalysis'])->name('reports.objection-analysis');
    Route::get('/reports/grading-activity', [ReportsController::class, 'gradingActivity'])->name('reports.grading-activity');
    Route::get('/reports/call-analytics', [CallAnalyticsController::class, 'index'])->name('reports.call-analytics');
    Route::get('/reports/call-analytics/export', [CallAnalyticsController::class, 'export'])->name('reports.call-analytics.export');

    // Performance Dashboard
    Route::get('/performance', [PerformanceController::class, 'index'])->name('performance.index');
    Route::get('/performance/{rep}', [PerformanceController::class, 'show'])->name('performance.show');
    Route::post('/performance/{rep}/share-all', [PerformanceController::class, 'shareAll'])->name('performance.share-all');

    // Call Queue
    Route::get('/calls', [CallQueueController::class, 'index'])->name('calls.index');
    Route::post('/calls/{call}/ignore', [CallQueueController::class, 'ignore'])->name('calls.ignore');
    Route::post('/calls/bulk-ignore', [CallQueueController::class, 'bulkIgnore'])->name('calls.bulk-ignore');
    Route::post('/calls/{call}/restore', [CallQueueController::class, 'restore'])->name('calls.restore');
    Route::post('/calls/{call}/skip', [GradingController::class, 'skip'])->name('calls.skip');

    // Transcription (rate limited - API calls are expensive)
    Route::get('/calls/{call}/process', [TranscriptionController::class, 'process'])->name('calls.process');
    Route::post('/calls/{call}/transcribe', [TranscriptionController::class, 'transcribe'])
        ->middleware('throttle:transcription')
        ->name('calls.transcribe');
    Route::get('/calls/{call}/audio', [TranscriptionController::class, 'audio'])->name('calls.audio');
    Route::get('/calls/{call}/recording-url', [TranscriptionController::class, 'getRecordingUrl'])->name('calls.recording-url');
    Route::post('/calls/{call}/page-time', [TranscriptionController::class, 'logPageTime'])->name('calls.page-time');

    // Grading (rate limited for state-changing operations)
    Route::get('/calls/{call}/grade', [GradingController::class, 'show'])->name('calls.grade');
    Route::post('/calls/{call}/grade', [GradingController::class, 'store'])
        ->middleware('throttle:grading')
        ->name('calls.grade.store');
    Route::get('/calls/{call}/audio-stream', [GradingController::class, 'audio'])->name('calls.audio-stream');
    Route::post('/calls/{call}/no-appointment', [GradingController::class, 'saveNoAppointmentReason'])
        ->middleware('throttle:grading')
        ->name('calls.no-appointment');
    Route::post('/calls/{call}/swap-speakers', [GradingController::class, 'swapSpeakers'])->name('calls.swap-speakers');
    Route::patch('/calls/{call}/details', [GradingController::class, 'updateCallDetails'])->name('calls.update-details');
    Route::post('/calls/{call}/refresh-salesforce', [GradingController::class, 'refreshSalesforce'])->name('calls.refresh-salesforce');
    Route::get('/calls/{call}/sharing-info', [GradingController::class, 'getSharingInfo'])->name('calls.sharing-info');
    Route::post('/calls/{call}/share', [GradingController::class, 'shareWithRep'])
        ->middleware('throttle:grading')
        ->name('calls.share');

    // Coaching Notes (API for grading page)
    Route::get('/notes/form-data', [CoachingNoteController::class, 'formData'])->name('notes.form-data');
    Route::get('/calls/{call}/notes', [CoachingNoteController::class, 'index'])->name('notes.index');
    Route::post('/calls/{call}/notes', [CoachingNoteController::class, 'store'])->name('notes.store');
    Route::patch('/notes/{note}', [CoachingNoteController::class, 'update'])->name('notes.update');
    Route::delete('/notes/{note}', [CoachingNoteController::class, 'destroy'])->name('notes.destroy');

    // Libraries
    Route::get('/graded-calls', [GradedCallsController::class, 'index'])->name('graded-calls');
    Route::get('/notes-library', [NotesLibraryController::class, 'index'])->name('notes-library');
    Route::get('/objections', [ObjectionsLibraryController::class, 'index'])->name('objections');
    Route::get('/golden-moments', [GoldenMomentsController::class, 'index'])->name('golden-moments');
    Route::get('/updates', [UpdatesController::class, 'index'])->name('updates');

});
