# Slice 11: Cost Dashboard + Grading Quality Metrics

## Objective
Build admin dashboards for monitoring transcription costs and grading quality metrics. Helps owners track expenses and identify managers who may be rushing through grades.

## Prerequisites
- **Slice 5 complete**: Transcription with cost logging
- **Slice 6 complete**: Grading with playback tracking
- **Slice 10 complete**: Admin layout and navigation

## What This Slice Builds

1. **Cost Dashboard** — Track transcription costs by day, office, project
2. **Grading Quality Dashboard** — Monitor manager grading patterns and flag suspicious grades
3. **Manager Leaderboard** — Compare manager activity and quality
4. **Detailed Grade Audit** — Drill into specific grades with low playback time

---

## File Structure

```
resources/
├── js/
│   └── Pages/Admin/
│       ├── Costs/
│       │   └── Index.vue              # Cost dashboard
│       ├── Quality/
│       │   ├── Index.vue              # Quality overview
│       │   ├── ManagerDetail.vue      # Single manager deep dive
│       │   └── GradeAudit.vue         # Flagged grades list
│       └── Leaderboard/
│           └── Index.vue              # Manager comparison
app/
├── Http/Controllers/Admin/
│   ├── CostDashboardController.php
│   ├── QualityDashboardController.php
│   └── LeaderboardController.php
```

---

## Step 1: Cost Dashboard Controller

Create `app/Http/Controllers/Admin/CostDashboardController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TranscriptionLog;
use App\Models\Call;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class CostDashboardController extends Controller
{
    public function index(Request $request)
    {
        $dateFrom = $request->get('date_from', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $dateTo = $request->get('date_to', Carbon::now()->format('Y-m-d'));

        // Overall stats
        $overallStats = TranscriptionLog::where('status', 'completed')
            ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->selectRaw('
                COUNT(*) as total_transcriptions,
                SUM(duration_seconds) as total_seconds,
                SUM(cost) as total_cost,
                AVG(cost) as avg_cost_per_call
            ')
            ->first();

        // Daily costs
        $dailyCosts = TranscriptionLog::where('status', 'completed')
            ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(cost) as cost'),
                DB::raw('SUM(duration_seconds) as seconds')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date')
            ->toArray();

        // Fill in missing days
        $period = Carbon::parse($dateFrom)->daysUntil($dateTo);
        $dailyData = [];
        foreach ($period as $date) {
            $key = $date->format('Y-m-d');
            $dailyData[$key] = $dailyCosts[$key] ?? [
                'date' => $key,
                'count' => 0,
                'cost' => 0,
                'seconds' => 0,
            ];
        }

        // Cost by office
        $costByOffice = TranscriptionLog::where('transcription_logs.status', 'completed')
            ->whereBetween('transcription_logs.created_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->join('calls', 'transcription_logs.call_id', '=', 'calls.id')
            ->join('accounts', 'calls.account_id', '=', 'accounts.id')
            ->select(
                'accounts.id',
                'accounts.name',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(transcription_logs.cost) as cost'),
                DB::raw('SUM(transcription_logs.duration_seconds) as seconds')
            )
            ->groupBy('accounts.id', 'accounts.name')
            ->orderBy('cost', 'desc')
            ->get();

        // Cost by project
        $costByProject = TranscriptionLog::where('transcription_logs.status', 'completed')
            ->whereBetween('transcription_logs.created_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->join('calls', 'transcription_logs.call_id', '=', 'calls.id')
            ->select(
                'calls.project_name',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(transcription_logs.cost) as cost'),
                DB::raw('SUM(transcription_logs.duration_seconds) as seconds')
            )
            ->groupBy('calls.project_name')
            ->orderBy('cost', 'desc')
            ->limit(10)
            ->get();

        // Failed transcriptions
        $failedCount = TranscriptionLog::where('status', 'failed')
            ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->count();

        // Month-over-month comparison
        $lastMonthStart = Carbon::parse($dateFrom)->subMonth()->format('Y-m-d');
        $lastMonthEnd = Carbon::parse($dateTo)->subMonth()->format('Y-m-d');
        
        $lastMonthStats = TranscriptionLog::where('status', 'completed')
            ->whereBetween('created_at', [$lastMonthStart, $lastMonthEnd . ' 23:59:59'])
            ->selectRaw('SUM(cost) as total_cost, COUNT(*) as total_count')
            ->first();

        return Inertia::render('Admin/Costs/Index', [
            'overallStats' => [
                'total_transcriptions' => (int) ($overallStats->total_transcriptions ?? 0),
                'total_minutes' => round(($overallStats->total_seconds ?? 0) / 60, 1),
                'total_cost' => round($overallStats->total_cost ?? 0, 2),
                'avg_cost_per_call' => round($overallStats->avg_cost_per_call ?? 0, 4),
                'cost_per_minute' => ($overallStats->total_seconds ?? 0) > 0 
                    ? round(($overallStats->total_cost ?? 0) / (($overallStats->total_seconds ?? 0) / 60), 4)
                    : 0,
            ],
            'dailyData' => array_values($dailyData),
            'costByOffice' => $costByOffice,
            'costByProject' => $costByProject,
            'failedCount' => $failedCount,
            'comparison' => [
                'last_month_cost' => round($lastMonthStats->total_cost ?? 0, 2),
                'last_month_count' => (int) ($lastMonthStats->total_count ?? 0),
                'cost_change' => $lastMonthStats->total_cost > 0
                    ? round((($overallStats->total_cost - $lastMonthStats->total_cost) / $lastMonthStats->total_cost) * 100, 1)
                    : 0,
            ],
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }
}
```

---

## Step 2: Quality Dashboard Controller

Create `app/Http/Controllers/Admin/QualityDashboardController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Grade;
use App\Models\User;
use App\Models\Call;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class QualityDashboardController extends Controller
{
    public function index(Request $request)
    {
        $dateFrom = $request->get('date_from', Carbon::now()->subDays(30)->format('Y-m-d'));
        $dateTo = $request->get('date_to', Carbon::now()->format('Y-m-d'));

        // Get thresholds from settings
        $flagThreshold = (int) Setting::get('grading_quality_flag_threshold', 25);
        $warnThreshold = (int) Setting::get('grading_quality_warn_threshold', 50);

        // Manager quality stats
        $managerStats = Grade::where('status', 'submitted')
            ->whereBetween('submitted_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->join('calls', 'grades.call_id', '=', 'calls.id')
            ->join('users', 'grades.graded_by', '=', 'users.id')
            ->select(
                'users.id as manager_id',
                'users.name as manager_name',
                DB::raw('COUNT(*) as total_grades'),
                DB::raw('AVG(grades.weighted_score) as avg_score'),
                DB::raw('AVG(grades.playback_seconds) as avg_playback'),
                DB::raw('AVG(calls.duration_seconds) as avg_call_duration'),
                DB::raw('AVG(CASE WHEN calls.duration_seconds > 0 THEN (grades.playback_seconds / calls.duration_seconds) * 100 ELSE 0 END) as avg_playback_ratio'),
                DB::raw("SUM(CASE WHEN calls.duration_seconds > 0 AND (grades.playback_seconds / calls.duration_seconds) * 100 < {$flagThreshold} THEN 1 ELSE 0 END) as flagged_count"),
                DB::raw("SUM(CASE WHEN calls.duration_seconds > 0 AND (grades.playback_seconds / calls.duration_seconds) * 100 >= {$flagThreshold} AND (grades.playback_seconds / calls.duration_seconds) * 100 < {$warnThreshold} THEN 1 ELSE 0 END) as warned_count")
            )
            ->groupBy('users.id', 'users.name')
            ->orderBy('flagged_count', 'desc')
            ->get()
            ->map(function($stat) {
                $stat->avg_playback_ratio = round($stat->avg_playback_ratio, 1);
                $stat->avg_score = round($stat->avg_score, 1);
                $stat->avg_playback_formatted = gmdate('i:s', $stat->avg_playback);
                $stat->avg_call_formatted = gmdate('i:s', $stat->avg_call_duration);
                return $stat;
            });

        // Overall quality stats
        $overallStats = Grade::where('status', 'submitted')
            ->whereBetween('submitted_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->join('calls', 'grades.call_id', '=', 'calls.id')
            ->selectRaw("
                COUNT(*) as total_grades,
                SUM(CASE WHEN calls.duration_seconds > 0 AND (grades.playback_seconds / calls.duration_seconds) * 100 < {$flagThreshold} THEN 1 ELSE 0 END) as flagged_count,
                SUM(CASE WHEN calls.duration_seconds > 0 AND (grades.playback_seconds / calls.duration_seconds) * 100 >= {$flagThreshold} AND (grades.playback_seconds / calls.duration_seconds) * 100 < {$warnThreshold} THEN 1 ELSE 0 END) as warned_count,
                AVG(CASE WHEN calls.duration_seconds > 0 THEN (grades.playback_seconds / calls.duration_seconds) * 100 ELSE 0 END) as avg_playback_ratio
            ")
            ->first();

        // Recent flagged grades
        $flaggedGrades = Grade::where('grades.status', 'submitted')
            ->whereBetween('grades.submitted_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->join('calls', 'grades.call_id', '=', 'calls.id')
            ->join('users', 'grades.graded_by', '=', 'users.id')
            ->whereRaw("calls.duration_seconds > 0 AND (grades.playback_seconds / calls.duration_seconds) * 100 < {$flagThreshold}")
            ->select(
                'grades.id',
                'grades.call_id',
                'grades.weighted_score',
                'grades.playback_seconds',
                'grades.submitted_at',
                'calls.duration_seconds',
                'calls.rep_name',
                'users.name as manager_name',
                DB::raw('ROUND((grades.playback_seconds / calls.duration_seconds) * 100, 1) as playback_ratio')
            )
            ->orderBy('grades.submitted_at', 'desc')
            ->limit(20)
            ->get();

        return Inertia::render('Admin/Quality/Index', [
            'managerStats' => $managerStats,
            'overallStats' => [
                'total_grades' => (int) $overallStats->total_grades,
                'flagged_count' => (int) $overallStats->flagged_count,
                'warned_count' => (int) $overallStats->warned_count,
                'avg_playback_ratio' => round($overallStats->avg_playback_ratio ?? 0, 1),
                'quality_rate' => $overallStats->total_grades > 0
                    ? round((($overallStats->total_grades - $overallStats->flagged_count - $overallStats->warned_count) / $overallStats->total_grades) * 100, 1)
                    : 100,
            ],
            'flaggedGrades' => $flaggedGrades,
            'thresholds' => [
                'flag' => $flagThreshold,
                'warn' => $warnThreshold,
            ],
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    public function managerDetail(Request $request, User $manager)
    {
        $dateFrom = $request->get('date_from', Carbon::now()->subDays(30)->format('Y-m-d'));
        $dateTo = $request->get('date_to', Carbon::now()->format('Y-m-d'));

        $flagThreshold = (int) Setting::get('grading_quality_flag_threshold', 25);
        $warnThreshold = (int) Setting::get('grading_quality_warn_threshold', 50);

        // All grades for this manager
        $grades = Grade::where('graded_by', $manager->id)
            ->where('status', 'submitted')
            ->whereBetween('submitted_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->join('calls', 'grades.call_id', '=', 'calls.id')
            ->select(
                'grades.id',
                'grades.call_id',
                'grades.weighted_score',
                'grades.playback_seconds',
                'grades.submitted_at',
                'calls.duration_seconds',
                'calls.rep_name',
                'calls.project_name',
                DB::raw('ROUND((grades.playback_seconds / NULLIF(calls.duration_seconds, 0)) * 100, 1) as playback_ratio')
            )
            ->orderBy('grades.submitted_at', 'desc')
            ->paginate(25)
            ->withQueryString();

        // Stats for this manager
        $stats = Grade::where('graded_by', $manager->id)
            ->where('status', 'submitted')
            ->whereBetween('submitted_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->join('calls', 'grades.call_id', '=', 'calls.id')
            ->selectRaw("
                COUNT(*) as total_grades,
                AVG(grades.weighted_score) as avg_score,
                AVG(CASE WHEN calls.duration_seconds > 0 THEN (grades.playback_seconds / calls.duration_seconds) * 100 ELSE 0 END) as avg_playback_ratio,
                SUM(CASE WHEN calls.duration_seconds > 0 AND (grades.playback_seconds / calls.duration_seconds) * 100 < {$flagThreshold} THEN 1 ELSE 0 END) as flagged_count,
                SUM(CASE WHEN calls.duration_seconds > 0 AND (grades.playback_seconds / calls.duration_seconds) * 100 >= {$flagThreshold} AND (grades.playback_seconds / calls.duration_seconds) * 100 < {$warnThreshold} THEN 1 ELSE 0 END) as warned_count
            ")
            ->first();

        // Daily activity
        $dailyActivity = Grade::where('graded_by', $manager->id)
            ->where('status', 'submitted')
            ->whereBetween('submitted_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->select(DB::raw('DATE(submitted_at) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->pluck('count', 'date')
            ->toArray();

        return Inertia::render('Admin/Quality/ManagerDetail', [
            'manager' => $manager->only(['id', 'name', 'email']),
            'grades' => $grades,
            'stats' => [
                'total_grades' => (int) $stats->total_grades,
                'avg_score' => round($stats->avg_score ?? 0, 1),
                'avg_playback_ratio' => round($stats->avg_playback_ratio ?? 0, 1),
                'flagged_count' => (int) $stats->flagged_count,
                'warned_count' => (int) $stats->warned_count,
            ],
            'dailyActivity' => $dailyActivity,
            'thresholds' => [
                'flag' => $flagThreshold,
                'warn' => $warnThreshold,
            ],
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    public function gradeAudit(Request $request)
    {
        $flagThreshold = (int) Setting::get('grading_quality_flag_threshold', 25);
        $warnThreshold = (int) Setting::get('grading_quality_warn_threshold', 50);
        
        $filter = $request->get('filter', 'flagged'); // flagged, warned, all
        $managerId = $request->get('manager');

        $query = Grade::where('grades.status', 'submitted')
            ->join('calls', 'grades.call_id', '=', 'calls.id')
            ->join('users', 'grades.graded_by', '=', 'users.id')
            ->select(
                'grades.id',
                'grades.call_id',
                'grades.weighted_score',
                'grades.playback_seconds',
                'grades.submitted_at',
                'calls.duration_seconds',
                'calls.rep_name',
                'calls.project_name',
                'calls.call_date',
                'users.id as manager_id',
                'users.name as manager_name',
                DB::raw('ROUND((grades.playback_seconds / NULLIF(calls.duration_seconds, 0)) * 100, 1) as playback_ratio')
            );

        if ($filter === 'flagged') {
            $query->whereRaw("calls.duration_seconds > 0 AND (grades.playback_seconds / calls.duration_seconds) * 100 < {$flagThreshold}");
        } elseif ($filter === 'warned') {
            $query->whereRaw("calls.duration_seconds > 0 AND (grades.playback_seconds / calls.duration_seconds) * 100 >= {$flagThreshold} AND (grades.playback_seconds / calls.duration_seconds) * 100 < {$warnThreshold}");
        }

        if ($managerId) {
            $query->where('grades.graded_by', $managerId);
        }

        $grades = $query->orderBy('grades.submitted_at', 'desc')
            ->paginate(50)
            ->withQueryString();

        $managers = User::whereIn('role', ['manager', 'site_admin'])
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Admin/Quality/GradeAudit', [
            'grades' => $grades,
            'managers' => $managers,
            'thresholds' => [
                'flag' => $flagThreshold,
                'warn' => $warnThreshold,
            ],
            'filters' => [
                'filter' => $filter,
                'manager' => $managerId,
            ],
        ]);
    }
}
```

---

## Step 3: Leaderboard Controller

Create `app/Http/Controllers/Admin/LeaderboardController.php`:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Grade;
use App\Models\CoachingNote;
use App\Models\User;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class LeaderboardController extends Controller
{
    public function index(Request $request)
    {
        $period = $request->get('period', 'week'); // week, month, quarter, all
        
        $dateFrom = match($period) {
            'week' => Carbon::now()->startOfWeek(),
            'month' => Carbon::now()->startOfMonth(),
            'quarter' => Carbon::now()->startOfQuarter(),
            default => Carbon::now()->subYears(10),
        };

        $flagThreshold = (int) Setting::get('grading_quality_flag_threshold', 25);

        // Manager leaderboard
        $leaderboard = User::whereIn('role', ['manager', 'site_admin'])
            ->where('is_active', true)
            ->leftJoin('grades', function($join) use ($dateFrom) {
                $join->on('users.id', '=', 'grades.graded_by')
                    ->where('grades.status', '=', 'submitted')
                    ->where('grades.submitted_at', '>=', $dateFrom);
            })
            ->leftJoin('calls', 'grades.call_id', '=', 'calls.id')
            ->select(
                'users.id',
                'users.name',
                DB::raw('COUNT(grades.id) as grades_count'),
                DB::raw('AVG(grades.weighted_score) as avg_score'),
                DB::raw('AVG(CASE WHEN calls.duration_seconds > 0 THEN (grades.playback_seconds / calls.duration_seconds) * 100 ELSE NULL END) as avg_playback_ratio'),
                DB::raw("SUM(CASE WHEN calls.duration_seconds > 0 AND (grades.playback_seconds / calls.duration_seconds) * 100 < {$flagThreshold} THEN 1 ELSE 0 END) as flagged_count")
            )
            ->groupBy('users.id', 'users.name')
            ->orderBy('grades_count', 'desc')
            ->get()
            ->map(function($user) {
                $user->avg_score = round($user->avg_score ?? 0, 1);
                $user->avg_playback_ratio = round($user->avg_playback_ratio ?? 0, 1);
                $user->quality_rate = $user->grades_count > 0
                    ? round((($user->grades_count - $user->flagged_count) / $user->grades_count) * 100, 1)
                    : 100;
                return $user;
            });

        // Add coaching notes count
        $noteCounts = CoachingNote::where('created_at', '>=', $dateFrom)
            ->select('author_id', DB::raw('COUNT(*) as count'))
            ->groupBy('author_id')
            ->pluck('count', 'author_id');

        $leaderboard = $leaderboard->map(function($user) use ($noteCounts) {
            $user->notes_count = $noteCounts[$user->id] ?? 0;
            return $user;
        });

        // Calculate rankings
        $byVolume = $leaderboard->sortByDesc('grades_count')->values();
        $byScore = $leaderboard->where('grades_count', '>=', 5)->sortByDesc('avg_score')->values();
        $byQuality = $leaderboard->where('grades_count', '>=', 5)->sortByDesc('quality_rate')->values();
        $byNotes = $leaderboard->sortByDesc('notes_count')->values();

        // Overall stats
        $overallStats = [
            'total_grades' => $leaderboard->sum('grades_count'),
            'total_notes' => $leaderboard->sum('notes_count'),
            'avg_grades_per_manager' => $leaderboard->count() > 0 
                ? round($leaderboard->sum('grades_count') / $leaderboard->count(), 1)
                : 0,
            'avg_score_all' => $leaderboard->where('grades_count', '>', 0)->avg('avg_score'),
        ];

        return Inertia::render('Admin/Leaderboard/Index', [
            'leaderboard' => $leaderboard->values(),
            'rankings' => [
                'byVolume' => $byVolume->take(5)->pluck('name', 'id'),
                'byScore' => $byScore->take(5)->pluck('name', 'id'),
                'byQuality' => $byQuality->take(5)->pluck('name', 'id'),
                'byNotes' => $byNotes->take(5)->pluck('name', 'id'),
            ],
            'overallStats' => $overallStats,
            'filters' => [
                'period' => $period,
            ],
        ]);
    }
}
```

---

## Step 4: Routes

Add to `routes/admin.php`:

```php
use App\Http\Controllers\Admin\CostDashboardController;
use App\Http\Controllers\Admin\QualityDashboardController;
use App\Http\Controllers\Admin\LeaderboardController;

Route::middleware(['auth', 'role:site_admin,system_admin'])->prefix('admin')->name('admin.')->group(function () {
    // ... existing routes ...

    // Cost Dashboard
    Route::get('/costs', [CostDashboardController::class, 'index'])->name('costs.index');

    // Quality Dashboard
    Route::get('/quality', [QualityDashboardController::class, 'index'])->name('quality.index');
    Route::get('/quality/manager/{manager}', [QualityDashboardController::class, 'managerDetail'])->name('quality.manager');
    Route::get('/quality/audit', [QualityDashboardController::class, 'gradeAudit'])->name('quality.audit');

    // Leaderboard
    Route::get('/leaderboard', [LeaderboardController::class, 'index'])->name('leaderboard.index');
});
```

---

## Step 5: Update Admin Navigation

Add to `resources/js/Layouts/AdminLayout.vue` nav links:

```vue
<AdminNavLink href="/admin/costs" :active="isActive('/admin/costs')">
  Costs
</AdminNavLink>
<AdminNavLink href="/admin/quality" :active="isActive('/admin/quality')">
  Quality
</AdminNavLink>
<AdminNavLink href="/admin/leaderboard" :active="isActive('/admin/leaderboard')">
  Leaderboard
</AdminNavLink>
```

---

## Step 6: Cost Dashboard Page

Create `resources/js/Pages/Admin/Costs/Index.vue`:

```vue
<template>
  <AdminLayout>
    <div class="max-w-7xl mx-auto px-4 py-6">
      <!-- Header -->
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Transcription Costs</h1>
        <p class="text-gray-600">Monitor Deepgram usage and expenses</p>
      </div>

      <!-- Filters -->
      <div class="bg-white rounded-lg shadow p-4 mb-6">
        <div class="flex gap-4 items-end">
          <div>
            <label class="block text-sm text-gray-600 mb-1">From</label>
            <input type="date" v-model="localFilters.date_from" class="border rounded px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="block text-sm text-gray-600 mb-1">To</label>
            <input type="date" v-model="localFilters.date_to" class="border rounded px-3 py-2 text-sm" />
          </div>
          <button @click="applyFilters" class="bg-blue-600 text-white rounded px-4 py-2 text-sm hover:bg-blue-700">
            Apply
          </button>
        </div>
      </div>

      <!-- Stats Cards -->
      <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-4">
          <p class="text-sm text-gray-500">Total Cost</p>
          <p class="text-2xl font-bold text-gray-900">${{ overallStats.total_cost.toFixed(2) }}</p>
          <p v-if="comparison.cost_change !== 0" :class="['text-xs', comparison.cost_change > 0 ? 'text-red-600' : 'text-green-600']">
            {{ comparison.cost_change > 0 ? '↑' : '↓' }} {{ Math.abs(comparison.cost_change) }}% vs last period
          </p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
          <p class="text-sm text-gray-500">Transcriptions</p>
          <p class="text-2xl font-bold text-blue-600">{{ overallStats.total_transcriptions }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
          <p class="text-sm text-gray-500">Total Minutes</p>
          <p class="text-2xl font-bold text-gray-900">{{ overallStats.total_minutes.toLocaleString() }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
          <p class="text-sm text-gray-500">Avg Cost/Call</p>
          <p class="text-2xl font-bold text-gray-900">${{ overallStats.avg_cost_per_call.toFixed(3) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
          <p class="text-sm text-gray-500">Cost/Minute</p>
          <p class="text-2xl font-bold text-gray-900">${{ overallStats.cost_per_minute.toFixed(4) }}</p>
        </div>
      </div>

      <!-- Failed Alert -->
      <div v-if="failedCount > 0" class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
        <p class="text-red-800">
          ⚠️ <strong>{{ failedCount }}</strong> failed transcriptions in this period. Check logs for details.
        </p>
      </div>

      <!-- Daily Chart -->
      <div class="bg-white rounded-lg shadow p-4 mb-6">
        <h3 class="font-medium text-gray-900 mb-4">Daily Costs</h3>
        <div class="h-48 flex items-end gap-1">
          <div 
            v-for="day in dailyData" 
            :key="day.date"
            class="flex-1 flex flex-col items-center"
          >
            <div 
              class="w-full bg-green-500 rounded-t hover:bg-green-600 transition-colors"
              :style="{ height: getBarHeight(day.cost) + '%', minHeight: day.cost > 0 ? '4px' : '0' }"
              :title="day.date + ': $' + day.cost.toFixed(2)"
            />
          </div>
        </div>
        <div class="flex justify-between mt-2 text-xs text-gray-400">
          <span>{{ dailyData[0]?.date }}</span>
          <span>{{ dailyData[dailyData.length - 1]?.date }}</span>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Cost by Office -->
        <div class="bg-white rounded-lg shadow">
          <div class="px-4 py-3 border-b">
            <h3 class="font-medium text-gray-900">Cost by Office</h3>
          </div>
          <div class="p-4">
            <div v-for="office in costByOffice" :key="office.id" class="flex items-center justify-between py-2">
              <span class="text-sm text-gray-700">{{ office.name }}</span>
              <div class="text-right">
                <span class="text-sm font-medium text-gray-900">${{ office.cost.toFixed(2) }}</span>
                <span class="text-xs text-gray-500 ml-2">({{ office.count }} calls)</span>
              </div>
            </div>
            <div v-if="costByOffice.length === 0" class="text-center text-gray-500 py-4">
              No data available.
            </div>
          </div>
        </div>

        <!-- Cost by Project -->
        <div class="bg-white rounded-lg shadow">
          <div class="px-4 py-3 border-b">
            <h3 class="font-medium text-gray-900">Top Projects by Cost</h3>
          </div>
          <div class="p-4">
            <div v-for="project in costByProject" :key="project.project_name" class="flex items-center justify-between py-2">
              <span class="text-sm text-gray-700">{{ project.project_name || 'Unknown' }}</span>
              <div class="text-right">
                <span class="text-sm font-medium text-gray-900">${{ project.cost.toFixed(2) }}</span>
                <span class="text-xs text-gray-500 ml-2">({{ project.count }} calls)</span>
              </div>
            </div>
            <div v-if="costByProject.length === 0" class="text-center text-gray-500 py-4">
              No data available.
            </div>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  overallStats: Object,
  dailyData: Array,
  costByOffice: Array,
  costByProject: Array,
  failedCount: Number,
  comparison: Object,
  filters: Object,
});

const localFilters = ref({ ...props.filters });

function applyFilters() {
  router.get(route('admin.costs.index'), localFilters.value, { preserveState: true });
}

const maxDailyCost = computed(() => Math.max(...props.dailyData.map(d => d.cost), 0.01));

function getBarHeight(cost) {
  return (cost / maxDailyCost.value) * 90;
}
</script>
```

---

## Step 7: Quality Dashboard Page

Create `resources/js/Pages/Admin/Quality/Index.vue`:

```vue
<template>
  <AdminLayout>
    <div class="max-w-7xl mx-auto px-4 py-6">
      <!-- Header -->
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Grading Quality</h1>
        <p class="text-gray-600">Monitor manager grading patterns and flag suspicious activity</p>
      </div>

      <!-- Filters -->
      <div class="bg-white rounded-lg shadow p-4 mb-6">
        <div class="flex gap-4 items-end">
          <div>
            <label class="block text-sm text-gray-600 mb-1">From</label>
            <input type="date" v-model="localFilters.date_from" class="border rounded px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="block text-sm text-gray-600 mb-1">To</label>
            <input type="date" v-model="localFilters.date_to" class="border rounded px-3 py-2 text-sm" />
          </div>
          <button @click="applyFilters" class="bg-blue-600 text-white rounded px-4 py-2 text-sm hover:bg-blue-700">
            Apply
          </button>
          <Link :href="route('admin.quality.audit')" class="text-blue-600 hover:text-blue-800 text-sm ml-auto">
            View All Flagged Grades →
          </Link>
        </div>
      </div>

      <!-- Overall Stats -->
      <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-4">
          <p class="text-sm text-gray-500">Total Grades</p>
          <p class="text-2xl font-bold text-gray-900">{{ overallStats.total_grades }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
          <p class="text-sm text-gray-500">Quality Rate</p>
          <p :class="['text-2xl font-bold', overallStats.quality_rate >= 90 ? 'text-green-600' : overallStats.quality_rate >= 75 ? 'text-yellow-600' : 'text-red-600']">
            {{ overallStats.quality_rate }}%
          </p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
          <p class="text-sm text-gray-500">Avg Playback Ratio</p>
          <p class="text-2xl font-bold text-gray-900">{{ overallStats.avg_playback_ratio }}%</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4 bg-red-50">
          <p class="text-sm text-red-600">Flagged (&lt;{{ thresholds.flag }}%)</p>
          <p class="text-2xl font-bold text-red-600">{{ overallStats.flagged_count }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4 bg-yellow-50">
          <p class="text-sm text-yellow-600">Warned ({{ thresholds.flag }}-{{ thresholds.warn }}%)</p>
          <p class="text-2xl font-bold text-yellow-600">{{ overallStats.warned_count }}</p>
        </div>
      </div>

      <!-- Manager Stats Table -->
      <div class="bg-white rounded-lg shadow overflow-hidden mb-6">
        <div class="px-4 py-3 border-b">
          <h3 class="font-medium text-gray-900">Manager Quality Stats</h3>
        </div>
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Manager</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Grades</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Avg Score</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Avg Playback</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Playback %</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Flagged</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Warned</th>
              <th class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <tr v-for="manager in managerStats" :key="manager.manager_id" class="hover:bg-gray-50">
              <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ manager.manager_name }}</td>
              <td class="px-4 py-3 text-sm text-gray-600">{{ manager.total_grades }}</td>
              <td class="px-4 py-3 text-sm text-gray-600">{{ manager.avg_score }}%</td>
              <td class="px-4 py-3 text-sm text-gray-600">{{ manager.avg_playback_formatted }}</td>
              <td class="px-4 py-3">
                <span :class="[
                  'text-sm font-medium',
                  manager.avg_playback_ratio >= thresholds.warn ? 'text-green-600' : 
                  manager.avg_playback_ratio >= thresholds.flag ? 'text-yellow-600' : 'text-red-600'
                ]">
                  {{ manager.avg_playback_ratio }}%
                </span>
              </td>
              <td class="px-4 py-3">
                <span v-if="manager.flagged_count > 0" class="text-sm font-medium text-red-600">
                  {{ manager.flagged_count }}
                </span>
                <span v-else class="text-sm text-gray-400">0</span>
              </td>
              <td class="px-4 py-3">
                <span v-if="manager.warned_count > 0" class="text-sm font-medium text-yellow-600">
                  {{ manager.warned_count }}
                </span>
                <span v-else class="text-sm text-gray-400">0</span>
              </td>
              <td class="px-4 py-3">
                <Link 
                  :href="route('admin.quality.manager', manager.manager_id)"
                  class="text-blue-600 hover:text-blue-800 text-sm"
                >
                  Details
                </Link>
              </td>
            </tr>
          </tbody>
        </table>
        <div v-if="managerStats.length === 0" class="p-8 text-center text-gray-500">
          No grading data in this period.
        </div>
      </div>

      <!-- Recent Flagged Grades -->
      <div v-if="flaggedGrades.length > 0" class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-4 py-3 border-b bg-red-50">
          <h3 class="font-medium text-red-800">Recent Flagged Grades</h3>
        </div>
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Manager</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rep</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Score</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Playback %</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
              <th class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <tr v-for="grade in flaggedGrades" :key="grade.id" class="hover:bg-gray-50">
              <td class="px-4 py-3 text-sm text-gray-900">{{ grade.manager_name }}</td>
              <td class="px-4 py-3 text-sm text-gray-600">{{ grade.rep_name }}</td>
              <td class="px-4 py-3 text-sm text-gray-600">{{ grade.weighted_score }}%</td>
              <td class="px-4 py-3">
                <span class="text-sm font-medium text-red-600">{{ grade.playback_ratio }}%</span>
              </td>
              <td class="px-4 py-3 text-sm text-gray-600">{{ formatDate(grade.submitted_at) }}</td>
              <td class="px-4 py-3">
                <Link 
                  :href="route('manager.grade', grade.call_id)"
                  class="text-blue-600 hover:text-blue-800 text-sm"
                >
                  View
                </Link>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  managerStats: Array,
  overallStats: Object,
  flaggedGrades: Array,
  thresholds: Object,
  filters: Object,
});

const localFilters = ref({ ...props.filters });

function applyFilters() {
  router.get(route('admin.quality.index'), localFilters.value, { preserveState: true });
}

function formatDate(date) {
  return new Date(date).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  });
}
</script>
```

---

## Step 8: Manager Detail Page

Create `resources/js/Pages/Admin/Quality/ManagerDetail.vue`:

```vue
<template>
  <AdminLayout>
    <div class="max-w-7xl mx-auto px-4 py-6">
      <!-- Header -->
      <div class="mb-6">
        <Link href="/admin/quality" class="text-gray-500 hover:text-gray-700 text-sm">
          ← Back to Quality Dashboard
        </Link>
        <h1 class="text-2xl font-bold text-gray-900 mt-2">{{ manager.name }}</h1>
        <p class="text-gray-600">{{ manager.email }}</p>
      </div>

      <!-- Stats -->
      <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-4">
          <p class="text-sm text-gray-500">Total Grades</p>
          <p class="text-2xl font-bold text-gray-900">{{ stats.total_grades }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
          <p class="text-sm text-gray-500">Avg Score</p>
          <p class="text-2xl font-bold text-blue-600">{{ stats.avg_score }}%</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
          <p class="text-sm text-gray-500">Avg Playback %</p>
          <p :class="['text-2xl font-bold', playbackColor]">{{ stats.avg_playback_ratio }}%</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4 bg-red-50">
          <p class="text-sm text-red-600">Flagged</p>
          <p class="text-2xl font-bold text-red-600">{{ stats.flagged_count }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4 bg-yellow-50">
          <p class="text-sm text-yellow-600">Warned</p>
          <p class="text-2xl font-bold text-yellow-600">{{ stats.warned_count }}</p>
        </div>
      </div>

      <!-- Activity Chart -->
      <div class="bg-white rounded-lg shadow p-4 mb-6">
        <h3 class="font-medium text-gray-900 mb-4">Daily Activity</h3>
        <div class="h-32 flex items-end gap-1">
          <div 
            v-for="(count, date) in dailyActivity" 
            :key="date"
            class="flex-1"
          >
            <div 
              class="w-full bg-blue-500 rounded-t"
              :style="{ height: getActivityHeight(count) + '%', minHeight: count > 0 ? '4px' : '0' }"
              :title="date + ': ' + count + ' grades'"
            />
          </div>
        </div>
      </div>

      <!-- Grades Table -->
      <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-4 py-3 border-b">
          <h3 class="font-medium text-gray-900">All Grades</h3>
        </div>
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rep</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Project</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Score</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Playback %</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
              <th class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <tr v-for="grade in grades.data" :key="grade.id" class="hover:bg-gray-50">
              <td class="px-4 py-3 text-sm text-gray-900">{{ grade.rep_name }}</td>
              <td class="px-4 py-3 text-sm text-gray-600">{{ grade.project_name }}</td>
              <td class="px-4 py-3 text-sm text-gray-600">{{ grade.weighted_score }}%</td>
              <td class="px-4 py-3">
                <span :class="['text-sm font-medium', getPlaybackColor(grade.playback_ratio)]">
                  {{ grade.playback_ratio ?? '—' }}%
                </span>
              </td>
              <td class="px-4 py-3">
                <span v-if="grade.playback_ratio < thresholds.flag" class="text-xs px-2 py-0.5 rounded bg-red-100 text-red-800">
                  Flagged
                </span>
                <span v-else-if="grade.playback_ratio < thresholds.warn" class="text-xs px-2 py-0.5 rounded bg-yellow-100 text-yellow-800">
                  Warned
                </span>
                <span v-else class="text-xs px-2 py-0.5 rounded bg-green-100 text-green-800">
                  OK
                </span>
              </td>
              <td class="px-4 py-3 text-sm text-gray-600">{{ formatDate(grade.submitted_at) }}</td>
              <td class="px-4 py-3">
                <Link 
                  :href="route('manager.grade', grade.call_id)"
                  class="text-blue-600 hover:text-blue-800 text-sm"
                >
                  View
                </Link>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Pagination -->
        <div v-if="grades.last_page > 1" class="px-4 py-3 border-t flex justify-center gap-1">
          <Link
            v-for="link in grades.links"
            :key="link.label"
            :href="link.url"
            :class="[
              'px-3 py-1 text-sm rounded',
              link.active ? 'bg-blue-600 text-white' : 'bg-gray-100 hover:bg-gray-200 text-gray-700',
              !link.url ? 'opacity-50 cursor-not-allowed' : ''
            ]"
            v-html="link.label"
          />
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  manager: Object,
  grades: Object,
  stats: Object,
  dailyActivity: Object,
  thresholds: Object,
  filters: Object,
});

const playbackColor = computed(() => {
  if (props.stats.avg_playback_ratio >= props.thresholds.warn) return 'text-green-600';
  if (props.stats.avg_playback_ratio >= props.thresholds.flag) return 'text-yellow-600';
  return 'text-red-600';
});

const maxActivity = computed(() => Math.max(...Object.values(props.dailyActivity), 1));

function getActivityHeight(count) {
  return (count / maxActivity.value) * 90;
}

function getPlaybackColor(ratio) {
  if (ratio === null) return 'text-gray-400';
  if (ratio >= props.thresholds.warn) return 'text-green-600';
  if (ratio >= props.thresholds.flag) return 'text-yellow-600';
  return 'text-red-600';
}

function formatDate(date) {
  return new Date(date).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  });
}
</script>
```

---

## Step 9: Grade Audit Page

Create `resources/js/Pages/Admin/Quality/GradeAudit.vue`:

```vue
<template>
  <AdminLayout>
    <div class="max-w-7xl mx-auto px-4 py-6">
      <!-- Header -->
      <div class="mb-6">
        <Link href="/admin/quality" class="text-gray-500 hover:text-gray-700 text-sm">
          ← Back to Quality Dashboard
        </Link>
        <h1 class="text-2xl font-bold text-gray-900 mt-2">Grade Audit</h1>
        <p class="text-gray-600">Review grades by quality status</p>
      </div>

      <!-- Filters -->
      <div class="bg-white rounded-lg shadow p-4 mb-6">
        <div class="flex gap-4 items-end flex-wrap">
          <div>
            <label class="block text-sm text-gray-600 mb-1">Status</label>
            <select v-model="localFilters.filter" class="border rounded px-3 py-2 text-sm">
              <option value="flagged">Flagged (&lt;{{ thresholds.flag }}%)</option>
              <option value="warned">Warned ({{ thresholds.flag }}-{{ thresholds.warn }}%)</option>
              <option value="all">All Grades</option>
            </select>
          </div>
          <div>
            <label class="block text-sm text-gray-600 mb-1">Manager</label>
            <select v-model="localFilters.manager" class="border rounded px-3 py-2 text-sm">
              <option value="">All Managers</option>
              <option v-for="m in managers" :key="m.id" :value="m.id">{{ m.name }}</option>
            </select>
          </div>
          <button @click="applyFilters" class="bg-blue-600 text-white rounded px-4 py-2 text-sm hover:bg-blue-700">
            Apply
          </button>
        </div>
      </div>

      <!-- Grades Table -->
      <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Manager</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rep</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Project</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Score</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Playback %</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Call Date</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Graded</th>
              <th class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <tr 
              v-for="grade in grades.data" 
              :key="grade.id" 
              :class="['hover:bg-gray-50', grade.playback_ratio < thresholds.flag ? 'bg-red-50' : grade.playback_ratio < thresholds.warn ? 'bg-yellow-50' : '']"
            >
              <td class="px-4 py-3 text-sm text-gray-900">{{ grade.manager_name }}</td>
              <td class="px-4 py-3 text-sm text-gray-600">{{ grade.rep_name }}</td>
              <td class="px-4 py-3 text-sm text-gray-600">{{ grade.project_name }}</td>
              <td class="px-4 py-3 text-sm text-gray-600">{{ grade.weighted_score }}%</td>
              <td class="px-4 py-3">
                <span :class="['text-sm font-medium', getPlaybackColor(grade.playback_ratio)]">
                  {{ grade.playback_ratio }}%
                </span>
              </td>
              <td class="px-4 py-3 text-sm text-gray-600">{{ formatDate(grade.call_date) }}</td>
              <td class="px-4 py-3 text-sm text-gray-600">{{ formatDate(grade.submitted_at) }}</td>
              <td class="px-4 py-3">
                <Link 
                  :href="route('manager.grade', grade.call_id)"
                  class="text-blue-600 hover:text-blue-800 text-sm"
                >
                  View
                </Link>
              </td>
            </tr>
          </tbody>
        </table>

        <div v-if="grades.data.length === 0" class="p-8 text-center text-gray-500">
          No grades match this filter.
        </div>

        <!-- Pagination -->
        <div v-if="grades.last_page > 1" class="px-4 py-3 border-t flex justify-between items-center">
          <p class="text-sm text-gray-600">
            Showing {{ grades.from }} to {{ grades.to }} of {{ grades.total }}
          </p>
          <div class="flex gap-1">
            <Link
              v-for="link in grades.links"
              :key="link.label"
              :href="link.url"
              :class="[
                'px-3 py-1 text-sm rounded',
                link.active ? 'bg-blue-600 text-white' : 'bg-gray-100 hover:bg-gray-200 text-gray-700',
                !link.url ? 'opacity-50 cursor-not-allowed' : ''
              ]"
              v-html="link.label"
            />
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  grades: Object,
  managers: Array,
  thresholds: Object,
  filters: Object,
});

const localFilters = ref({ ...props.filters });

function applyFilters() {
  router.get(route('admin.quality.audit'), localFilters.value, { preserveState: true });
}

function getPlaybackColor(ratio) {
  if (ratio >= props.thresholds.warn) return 'text-green-600';
  if (ratio >= props.thresholds.flag) return 'text-yellow-600';
  return 'text-red-600';
}

function formatDate(date) {
  return new Date(date).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
  });
}
</script>
```

---

## Step 10: Leaderboard Page

Create `resources/js/Pages/Admin/Leaderboard/Index.vue`:

```vue
<template>
  <AdminLayout>
    <div class="max-w-7xl mx-auto px-4 py-6">
      <!-- Header -->
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Manager Leaderboard</h1>
        <p class="text-gray-600">Compare manager activity and performance</p>
      </div>

      <!-- Period Filter -->
      <div class="bg-white rounded-lg shadow p-4 mb-6">
        <div class="flex gap-2">
          <button
            v-for="p in periods"
            :key="p.value"
            @click="setPeriod(p.value)"
            :class="[
              'px-4 py-2 text-sm rounded transition-colors',
              filters.period === p.value 
                ? 'bg-blue-600 text-white' 
                : 'bg-gray-100 hover:bg-gray-200 text-gray-700'
            ]"
          >
            {{ p.label }}
          </button>
        </div>
      </div>

      <!-- Overall Stats -->
      <div class="grid grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-4">
          <p class="text-sm text-gray-500">Total Grades</p>
          <p class="text-2xl font-bold text-gray-900">{{ overallStats.total_grades }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
          <p class="text-sm text-gray-500">Total Notes</p>
          <p class="text-2xl font-bold text-blue-600">{{ overallStats.total_notes }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
          <p class="text-sm text-gray-500">Avg Per Manager</p>
          <p class="text-2xl font-bold text-gray-900">{{ overallStats.avg_grades_per_manager }}</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
          <p class="text-sm text-gray-500">Avg Score</p>
          <p class="text-2xl font-bold text-green-600">{{ Math.round(overallStats.avg_score_all) }}%</p>
        </div>
      </div>

      <!-- Top Rankings -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-4">
          <h3 class="text-sm font-medium text-gray-500 mb-2">🏆 Most Grades</h3>
          <ol class="space-y-1">
            <li v-for="(name, id, index) in rankings.byVolume" :key="id" class="text-sm">
              <span class="text-gray-400">{{ index + 1 }}.</span> {{ name }}
            </li>
          </ol>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
          <h3 class="text-sm font-medium text-gray-500 mb-2">⭐ Highest Scores</h3>
          <ol class="space-y-1">
            <li v-for="(name, id, index) in rankings.byScore" :key="id" class="text-sm">
              <span class="text-gray-400">{{ index + 1 }}.</span> {{ name }}
            </li>
          </ol>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
          <h3 class="text-sm font-medium text-gray-500 mb-2">✅ Best Quality</h3>
          <ol class="space-y-1">
            <li v-for="(name, id, index) in rankings.byQuality" :key="id" class="text-sm">
              <span class="text-gray-400">{{ index + 1 }}.</span> {{ name }}
            </li>
          </ol>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
          <h3 class="text-sm font-medium text-gray-500 mb-2">📝 Most Notes</h3>
          <ol class="space-y-1">
            <li v-for="(name, id, index) in rankings.byNotes" :key="id" class="text-sm">
              <span class="text-gray-400">{{ index + 1 }}.</span> {{ name }}
            </li>
          </ol>
        </div>
      </div>

      <!-- Full Leaderboard Table -->
      <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Manager</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Grades</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Notes</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Avg Score</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Playback %</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Quality Rate</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Flagged</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <tr v-for="manager in leaderboard" :key="manager.id" class="hover:bg-gray-50">
              <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ manager.name }}</td>
              <td class="px-4 py-3 text-sm text-gray-600">{{ manager.grades_count }}</td>
              <td class="px-4 py-3 text-sm text-gray-600">{{ manager.notes_count }}</td>
              <td class="px-4 py-3">
                <span v-if="manager.grades_count > 0" :class="['text-sm font-medium', scoreColor(manager.avg_score)]">
                  {{ manager.avg_score }}%
                </span>
                <span v-else class="text-sm text-gray-400">—</span>
              </td>
              <td class="px-4 py-3">
                <span v-if="manager.grades_count > 0" class="text-sm text-gray-600">
                  {{ manager.avg_playback_ratio }}%
                </span>
                <span v-else class="text-sm text-gray-400">—</span>
              </td>
              <td class="px-4 py-3">
                <span v-if="manager.grades_count > 0" :class="['text-sm font-medium', manager.quality_rate >= 90 ? 'text-green-600' : manager.quality_rate >= 75 ? 'text-yellow-600' : 'text-red-600']">
                  {{ manager.quality_rate }}%
                </span>
                <span v-else class="text-sm text-gray-400">—</span>
              </td>
              <td class="px-4 py-3">
                <span v-if="manager.flagged_count > 0" class="text-sm text-red-600">{{ manager.flagged_count }}</span>
                <span v-else class="text-sm text-gray-400">0</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
  leaderboard: Array,
  rankings: Object,
  overallStats: Object,
  filters: Object,
});

const periods = [
  { value: 'week', label: 'This Week' },
  { value: 'month', label: 'This Month' },
  { value: 'quarter', label: 'This Quarter' },
  { value: 'all', label: 'All Time' },
];

function setPeriod(period) {
  router.get(route('admin.leaderboard.index'), { period }, { preserveState: true });
}

function scoreColor(score) {
  if (score >= 85) return 'text-green-600';
  if (score >= 70) return 'text-blue-600';
  if (score >= 50) return 'text-yellow-600';
  return 'text-red-600';
}
</script>
```

---

## Verification Checklist

After implementation:

**Admin Navigation:**
- [ ] Costs, Quality, Leaderboard links added
- [ ] Active states work correctly

**Cost Dashboard:**
- [ ] Stats cards show totals
- [ ] Daily cost chart renders
- [ ] Cost by office breakdown
- [ ] Cost by project breakdown
- [ ] Month-over-month comparison
- [ ] Failed transcription warning
- [ ] Date filters work

**Quality Dashboard:**
- [ ] Overall quality stats display
- [ ] Manager stats table with all columns
- [ ] Flagged/warned counts accurate
- [ ] Click manager → detail page
- [ ] Recent flagged grades list
- [ ] Date filters work

**Manager Detail Page:**
- [ ] Stats for specific manager
- [ ] Daily activity chart
- [ ] All grades listed with status
- [ ] Pagination works
- [ ] Back link works

**Grade Audit Page:**
- [ ] Filter by status (flagged/warned/all)
- [ ] Filter by manager
- [ ] Row highlighting by status
- [ ] View links work

**Leaderboard:**
- [ ] Period selector works (week/month/quarter/all)
- [ ] Top rankings cards populate
- [ ] Full leaderboard table
- [ ] Quality rate calculated
- [ ] Notes count included

---

## Test Flow

1. Transcribe several calls (generates cost data)
2. Grade calls with varying playback times
3. Visit `/admin/costs` → see transcription costs
4. Visit `/admin/quality` → see quality metrics
5. Click a manager → see their detail page
6. Click "View All Flagged Grades" → audit page
7. Filter by status and manager
8. Visit `/admin/leaderboard` → see rankings
9. Switch periods → data updates

---

## Notes

- Playback ratio = (playback_seconds / call_duration) × 100
- Flagged = below flag threshold (default 25%)
- Warned = between flag and warn thresholds (25-50%)
- Quality rate = percentage of grades not flagged or warned
- Cost data requires TranscriptionLog table (from Slice 5)
- Thresholds are configurable in Settings (Slice 10)
