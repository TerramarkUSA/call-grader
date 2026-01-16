# Slice 9: Reports + Dashboard

## Objective
Build a manager dashboard with performance metrics, grading activity, and visual reports. Provides at-a-glance insights into grading patterns and rep performance trends.

## Prerequisites
- **Slice 6 complete**: Grading UI working
- **Slice 7 complete**: Notes and objections working
- **Slice 8 complete**: Libraries and navigation working

## What This Slice Builds

1. **Manager Dashboard** — Landing page with key metrics and activity
2. **Rep Performance Report** — Scores by rep with trends
3. **Category Breakdown Report** — Which rubric categories are weakest
4. **Objection Analysis Report** — Objection types and success rates
5. **Grading Activity Report** — Manager's own grading volume over time

---

## File Structure

```
resources/
├── js/
│   └── Pages/Manager/
│       ├── Dashboard/
│       │   └── Index.vue              # Main dashboard
│       └── Reports/
│           ├── RepPerformance.vue     # Rep scores report
│           ├── CategoryBreakdown.vue  # Category analysis
│           ├── ObjectionAnalysis.vue  # Objection stats
│           └── GradingActivity.vue    # Manager activity
app/
├── Http/Controllers/Manager/
│   ├── DashboardController.php
│   └── ReportsController.php
```

---

## Step 1: Dashboard Controller

Create `app/Http/Controllers/Manager/DashboardController.php`:

```php
<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Call;
use App\Models\Grade;
use App\Models\CoachingNote;
use App\Models\GradeCategoryScore;
use App\Models\RubricCategory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        $userId = Auth::id();
        $now = Carbon::now();
        $startOfWeek = $now->copy()->startOfWeek();
        $startOfMonth = $now->copy()->startOfMonth();

        // Grading stats
        $gradingStats = [
            'total_graded' => Grade::where('graded_by', $userId)->where('status', 'submitted')->count(),
            'graded_this_week' => Grade::where('graded_by', $userId)
                ->where('status', 'submitted')
                ->where('submitted_at', '>=', $startOfWeek)
                ->count(),
            'graded_this_month' => Grade::where('graded_by', $userId)
                ->where('status', 'submitted')
                ->where('submitted_at', '>=', $startOfMonth)
                ->count(),
            'drafts_pending' => Grade::where('graded_by', $userId)->where('status', 'draft')->count(),
        ];

        // Average scores
        $avgScore = Grade::where('graded_by', $userId)
            ->where('status', 'submitted')
            ->avg('weighted_score');

        $avgScoreThisWeek = Grade::where('graded_by', $userId)
            ->where('status', 'submitted')
            ->where('submitted_at', '>=', $startOfWeek)
            ->avg('weighted_score');

        // Calls in queue (ungraded)
        $callsInQueue = Call::whereDoesntHave('grades', function($q) use ($userId) {
                $q->where('graded_by', $userId);
            })
            ->where('status', 'pending')
            ->where('is_ignored', false)
            ->count();

        // Recent graded calls (last 5)
        $recentGrades = Grade::where('graded_by', $userId)
            ->where('status', 'submitted')
            ->with('call:id,rep_name,project_name,call_date')
            ->orderBy('submitted_at', 'desc')
            ->limit(5)
            ->get(['id', 'call_id', 'weighted_score', 'appointment_quality', 'submitted_at']);

        // Grading activity last 7 days
        $activityByDay = Grade::where('graded_by', $userId)
            ->where('status', 'submitted')
            ->where('submitted_at', '>=', $now->copy()->subDays(7))
            ->select(DB::raw('DATE(submitted_at) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->pluck('count', 'date')
            ->toArray();

        // Fill in missing days
        $last7Days = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = $now->copy()->subDays($i)->format('Y-m-d');
            $last7Days[$date] = $activityByDay[$date] ?? 0;
        }

        // Top reps (by average score from this manager's grades)
        $topReps = Grade::where('graded_by', $userId)
            ->where('status', 'submitted')
            ->join('calls', 'grades.call_id', '=', 'calls.id')
            ->select('calls.rep_name', DB::raw('AVG(weighted_score) as avg_score'), DB::raw('COUNT(*) as call_count'))
            ->groupBy('calls.rep_name')
            ->having('call_count', '>=', 3)
            ->orderBy('avg_score', 'desc')
            ->limit(5)
            ->get();

        // Bottom reps (need coaching)
        $bottomReps = Grade::where('graded_by', $userId)
            ->where('status', 'submitted')
            ->join('calls', 'grades.call_id', '=', 'calls.id')
            ->select('calls.rep_name', DB::raw('AVG(weighted_score) as avg_score'), DB::raw('COUNT(*) as call_count'))
            ->groupBy('calls.rep_name')
            ->having('call_count', '>=', 3)
            ->orderBy('avg_score', 'asc')
            ->limit(5)
            ->get();

        // Weakest categories (lowest average scores)
        $weakestCategories = GradeCategoryScore::whereHas('grade', function($q) use ($userId) {
                $q->where('graded_by', $userId)->where('status', 'submitted');
            })
            ->join('rubric_categories', 'grade_category_scores.category_id', '=', 'rubric_categories.id')
            ->select('rubric_categories.name', DB::raw('AVG(score) as avg_score'))
            ->groupBy('rubric_categories.id', 'rubric_categories.name')
            ->orderBy('avg_score', 'asc')
            ->limit(3)
            ->get();

        // Notes and objections stats
        $notesStats = [
            'total_notes' => CoachingNote::where('author_id', $userId)->count(),
            'notes_this_week' => CoachingNote::where('author_id', $userId)
                ->where('created_at', '>=', $startOfWeek)
                ->count(),
            'objections_logged' => CoachingNote::where('author_id', $userId)
                ->where('is_objection', true)
                ->count(),
            'objections_overcame' => CoachingNote::where('author_id', $userId)
                ->where('is_objection', true)
                ->where('objection_outcome', 'overcame')
                ->count(),
        ];

        return Inertia::render('Manager/Dashboard/Index', [
            'gradingStats' => $gradingStats,
            'avgScore' => round($avgScore ?? 0, 1),
            'avgScoreThisWeek' => round($avgScoreThisWeek ?? 0, 1),
            'callsInQueue' => $callsInQueue,
            'recentGrades' => $recentGrades,
            'activityByDay' => $last7Days,
            'topReps' => $topReps,
            'bottomReps' => $bottomReps,
            'weakestCategories' => $weakestCategories,
            'notesStats' => $notesStats,
        ]);
    }
}
```

---

## Step 2: Reports Controller

Create `app/Http/Controllers/Manager/ReportsController.php`:

```php
<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Grade;
use App\Models\GradeCategoryScore;
use App\Models\CoachingNote;
use App\Models\RubricCategory;
use App\Models\ObjectionType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class ReportsController extends Controller
{
    /**
     * Rep Performance Report
     */
    public function repPerformance(Request $request)
    {
        $userId = Auth::id();
        $dateFrom = $request->get('date_from', Carbon::now()->subDays(30)->format('Y-m-d'));
        $dateTo = $request->get('date_to', Carbon::now()->format('Y-m-d'));

        // Get all reps with grades in date range
        $repStats = Grade::where('graded_by', $userId)
            ->where('status', 'submitted')
            ->whereBetween('submitted_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->join('calls', 'grades.call_id', '=', 'calls.id')
            ->select(
                'calls.rep_name',
                DB::raw('COUNT(*) as call_count'),
                DB::raw('AVG(weighted_score) as avg_score'),
                DB::raw('MIN(weighted_score) as min_score'),
                DB::raw('MAX(weighted_score) as max_score'),
                DB::raw('SUM(CASE WHEN appointment_quality = "solid" THEN 1 ELSE 0 END) as solid_count'),
                DB::raw('SUM(CASE WHEN appointment_quality = "tentative" THEN 1 ELSE 0 END) as tentative_count'),
                DB::raw('SUM(CASE WHEN appointment_quality = "backed_in" THEN 1 ELSE 0 END) as backed_in_count')
            )
            ->groupBy('calls.rep_name')
            ->orderBy('avg_score', 'desc')
            ->get();

        // Trend data (weekly averages per rep)
        $trendData = Grade::where('graded_by', $userId)
            ->where('status', 'submitted')
            ->whereBetween('submitted_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->join('calls', 'grades.call_id', '=', 'calls.id')
            ->select(
                'calls.rep_name',
                DB::raw('YEARWEEK(submitted_at) as week'),
                DB::raw('AVG(weighted_score) as avg_score')
            )
            ->groupBy('calls.rep_name', 'week')
            ->orderBy('week')
            ->get()
            ->groupBy('rep_name');

        return Inertia::render('Manager/Reports/RepPerformance', [
            'repStats' => $repStats,
            'trendData' => $trendData,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    /**
     * Category Breakdown Report
     */
    public function categoryBreakdown(Request $request)
    {
        $userId = Auth::id();
        $dateFrom = $request->get('date_from', Carbon::now()->subDays(30)->format('Y-m-d'));
        $dateTo = $request->get('date_to', Carbon::now()->format('Y-m-d'));
        $repFilter = $request->get('rep');

        $query = GradeCategoryScore::whereHas('grade', function($q) use ($userId, $dateFrom, $dateTo) {
            $q->where('graded_by', $userId)
              ->where('status', 'submitted')
              ->whereBetween('submitted_at', [$dateFrom, $dateTo . ' 23:59:59']);
        });

        if ($repFilter) {
            $query->whereHas('grade.call', fn($q) => $q->where('rep_name', $repFilter));
        }

        $categoryStats = $query
            ->join('rubric_categories', 'grade_category_scores.category_id', '=', 'rubric_categories.id')
            ->select(
                'rubric_categories.id',
                'rubric_categories.name',
                'rubric_categories.weight',
                DB::raw('AVG(score) as avg_score'),
                DB::raw('COUNT(*) as sample_count'),
                DB::raw('SUM(CASE WHEN score = 4 THEN 1 ELSE 0 END) as score_4_count'),
                DB::raw('SUM(CASE WHEN score = 3 THEN 1 ELSE 0 END) as score_3_count'),
                DB::raw('SUM(CASE WHEN score = 2 THEN 1 ELSE 0 END) as score_2_count'),
                DB::raw('SUM(CASE WHEN score = 1 THEN 1 ELSE 0 END) as score_1_count')
            )
            ->groupBy('rubric_categories.id', 'rubric_categories.name', 'rubric_categories.weight')
            ->orderBy('rubric_categories.sort_order')
            ->get();

        // Get reps for filter dropdown
        $reps = Grade::where('graded_by', $userId)
            ->where('status', 'submitted')
            ->join('calls', 'grades.call_id', '=', 'calls.id')
            ->distinct()
            ->pluck('calls.rep_name')
            ->sort()
            ->values();

        return Inertia::render('Manager/Reports/CategoryBreakdown', [
            'categoryStats' => $categoryStats,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'rep' => $repFilter,
            ],
            'filterOptions' => [
                'reps' => $reps,
            ],
        ]);
    }

    /**
     * Objection Analysis Report
     */
    public function objectionAnalysis(Request $request)
    {
        $userId = Auth::id();
        $dateFrom = $request->get('date_from', Carbon::now()->subDays(30)->format('Y-m-d'));
        $dateTo = $request->get('date_to', Carbon::now()->format('Y-m-d'));

        // Stats by objection type
        $objectionStats = CoachingNote::where('author_id', $userId)
            ->where('is_objection', true)
            ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->join('objection_types', 'coaching_notes.objection_type_id', '=', 'objection_types.id')
            ->select(
                'objection_types.id',
                'objection_types.name',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN objection_outcome = "overcame" THEN 1 ELSE 0 END) as overcame'),
                DB::raw('SUM(CASE WHEN objection_outcome = "failed" THEN 1 ELSE 0 END) as failed')
            )
            ->groupBy('objection_types.id', 'objection_types.name')
            ->orderBy('total', 'desc')
            ->get()
            ->map(function($item) {
                $item->success_rate = $item->total > 0 
                    ? round(($item->overcame / $item->total) * 100, 1) 
                    : 0;
                return $item;
            });

        // Stats by rep
        $repObjectionStats = CoachingNote::where('author_id', $userId)
            ->where('is_objection', true)
            ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->join('calls', 'coaching_notes.call_id', '=', 'calls.id')
            ->select(
                'calls.rep_name',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN objection_outcome = "overcame" THEN 1 ELSE 0 END) as overcame'),
                DB::raw('SUM(CASE WHEN objection_outcome = "failed" THEN 1 ELSE 0 END) as failed')
            )
            ->groupBy('calls.rep_name')
            ->orderBy('total', 'desc')
            ->get()
            ->map(function($item) {
                $item->success_rate = $item->total > 0 
                    ? round(($item->overcame / $item->total) * 100, 1) 
                    : 0;
                return $item;
            });

        // Overall stats
        $overallStats = [
            'total' => CoachingNote::where('author_id', $userId)
                ->where('is_objection', true)
                ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
                ->count(),
            'overcame' => CoachingNote::where('author_id', $userId)
                ->where('is_objection', true)
                ->where('objection_outcome', 'overcame')
                ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
                ->count(),
            'failed' => CoachingNote::where('author_id', $userId)
                ->where('is_objection', true)
                ->where('objection_outcome', 'failed')
                ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
                ->count(),
        ];
        $overallStats['success_rate'] = $overallStats['total'] > 0 
            ? round(($overallStats['overcame'] / $overallStats['total']) * 100, 1) 
            : 0;

        return Inertia::render('Manager/Reports/ObjectionAnalysis', [
            'objectionStats' => $objectionStats,
            'repObjectionStats' => $repObjectionStats,
            'overallStats' => $overallStats,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }

    /**
     * Grading Activity Report
     */
    public function gradingActivity(Request $request)
    {
        $userId = Auth::id();
        $period = $request->get('period', '30'); // days

        $startDate = Carbon::now()->subDays((int)$period);

        // Daily grading counts
        $dailyActivity = Grade::where('graded_by', $userId)
            ->where('status', 'submitted')
            ->where('submitted_at', '>=', $startDate)
            ->select(DB::raw('DATE(submitted_at) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->pluck('count', 'date')
            ->toArray();

        // Fill in missing days
        $activityData = [];
        for ($i = (int)$period - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $activityData[$date] = $dailyActivity[$date] ?? 0;
        }

        // Stats
        $stats = [
            'total_period' => array_sum($activityData),
            'daily_average' => round(array_sum($activityData) / count($activityData), 1),
            'best_day' => max($activityData),
            'streak' => $this->calculateStreak($activityData),
        ];

        // Hourly distribution (what time do they grade?)
        $hourlyDistribution = Grade::where('graded_by', $userId)
            ->where('status', 'submitted')
            ->where('submitted_at', '>=', $startDate)
            ->select(DB::raw('HOUR(submitted_at) as hour'), DB::raw('COUNT(*) as count'))
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->pluck('count', 'hour')
            ->toArray();

        // Day of week distribution
        $dayOfWeekDistribution = Grade::where('graded_by', $userId)
            ->where('status', 'submitted')
            ->where('submitted_at', '>=', $startDate)
            ->select(DB::raw('DAYOFWEEK(submitted_at) as day'), DB::raw('COUNT(*) as count'))
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->pluck('count', 'day')
            ->toArray();

        return Inertia::render('Manager/Reports/GradingActivity', [
            'activityData' => $activityData,
            'hourlyDistribution' => $hourlyDistribution,
            'dayOfWeekDistribution' => $dayOfWeekDistribution,
            'stats' => $stats,
            'filters' => [
                'period' => $period,
            ],
        ]);
    }

    private function calculateStreak(array $activityData): int
    {
        $streak = 0;
        $dates = array_reverse(array_keys($activityData));
        
        foreach ($dates as $date) {
            if ($activityData[$date] > 0) {
                $streak++;
            } else {
                break;
            }
        }
        
        return $streak;
    }
}
```

---

## Step 3: Routes

Add to `routes/manager.php`:

```php
use App\Http\Controllers\Manager\DashboardController;
use App\Http\Controllers\Manager\ReportsController;

Route::middleware(['auth', 'role:manager'])->prefix('manager')->name('manager.')->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Reports
    Route::get('/reports/rep-performance', [ReportsController::class, 'repPerformance'])->name('reports.rep-performance');
    Route::get('/reports/category-breakdown', [ReportsController::class, 'categoryBreakdown'])->name('reports.category-breakdown');
    Route::get('/reports/objection-analysis', [ReportsController::class, 'objectionAnalysis'])->name('reports.objection-analysis');
    Route::get('/reports/grading-activity', [ReportsController::class, 'gradingActivity'])->name('reports.grading-activity');

    // ... existing routes ...
});
```

---

## Step 4: Update Navigation

Update `resources/js/Layouts/ManagerLayout.vue` to add Dashboard and Reports:

In the nav links section, add after "Objections":

```vue
<NavLink 
  :href="route('manager.dashboard')" 
  :active="isActive('manager.dashboard')"
>
  <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/>
  </svg>
  Dashboard
</NavLink>

<!-- Reports Dropdown -->
<div class="relative" v-click-outside="() => showReportsMenu = false">
  <button 
    @click="showReportsMenu = !showReportsMenu"
    :class="[
      'inline-flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors',
      isActive('manager.reports') 
        ? 'bg-blue-50 text-blue-700' 
        : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50'
    ]"
  >
    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
    </svg>
    Reports
    <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
    </svg>
  </button>

  <div 
    v-if="showReportsMenu"
    class="absolute left-0 mt-1 w-48 bg-white rounded-md shadow-lg py-1 z-50"
  >
    <Link
      :href="route('manager.reports.rep-performance')"
      class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
    >
      Rep Performance
    </Link>
    <Link
      :href="route('manager.reports.category-breakdown')"
      class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
    >
      Category Breakdown
    </Link>
    <Link
      :href="route('manager.reports.objection-analysis')"
      class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
    >
      Objection Analysis
    </Link>
    <Link
      :href="route('manager.reports.grading-activity')"
      class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
    >
      My Grading Activity
    </Link>
  </div>
</div>
```

Add to script setup:

```js
const showReportsMenu = ref(false);
```

---

## Step 5: Dashboard Page

Create `resources/js/Pages/Manager/Dashboard/Index.vue`:

```vue
<template>
  <ManagerLayout>
    <div class="max-w-7xl mx-auto px-4 py-6">
      <!-- Header -->
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>
        <p class="text-gray-600">Your grading performance at a glance</p>
      </div>

      <!-- Top Stats Row -->
      <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <StatCard 
          title="Calls in Queue" 
          :value="callsInQueue"
          icon="phone"
          :link="route('manager.queue')"
          linkText="View Queue"
        />
        <StatCard 
          title="Graded This Week" 
          :value="gradingStats.graded_this_week"
          icon="check"
        />
        <StatCard 
          title="Avg Score (All Time)" 
          :value="avgScore + '%'"
          :valueColor="scoreColor(avgScore)"
          icon="chart"
        />
        <StatCard 
          title="Avg Score (This Week)" 
          :value="avgScoreThisWeek + '%'"
          :valueColor="scoreColor(avgScoreThisWeek)"
          icon="trending"
        />
      </div>

      <!-- Main Content Grid -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left Column: Activity Chart + Recent Grades -->
        <div class="lg:col-span-2 space-y-6">
          <!-- Activity Chart -->
          <div class="bg-white rounded-lg shadow p-4">
            <h3 class="font-medium text-gray-900 mb-4">Grading Activity (Last 7 Days)</h3>
            <div class="h-48">
              <ActivityChart :data="activityByDay" />
            </div>
          </div>

          <!-- Recent Grades -->
          <div class="bg-white rounded-lg shadow">
            <div class="px-4 py-3 border-b flex items-center justify-between">
              <h3 class="font-medium text-gray-900">Recent Grades</h3>
              <Link 
                :href="route('manager.graded-calls')"
                class="text-sm text-blue-600 hover:text-blue-800"
              >
                View All →
              </Link>
            </div>
            <div class="divide-y">
              <div 
                v-for="grade in recentGrades" 
                :key="grade.id"
                class="px-4 py-3 flex items-center justify-between hover:bg-gray-50"
              >
                <div>
                  <p class="font-medium text-gray-900">{{ grade.call.rep_name }}</p>
                  <p class="text-sm text-gray-500">{{ grade.call.project_name }}</p>
                </div>
                <div class="text-right">
                  <p :class="['font-medium', scoreColor(grade.weighted_score)]">
                    {{ grade.weighted_score }}%
                  </p>
                  <p class="text-xs text-gray-400">{{ formatDate(grade.submitted_at) }}</p>
                </div>
              </div>
              <div v-if="recentGrades.length === 0" class="px-4 py-8 text-center text-gray-500">
                No grades yet. Start grading calls!
              </div>
            </div>
          </div>
        </div>

        <!-- Right Column: Insights -->
        <div class="space-y-6">
          <!-- Drafts Pending -->
          <div v-if="gradingStats.drafts_pending > 0" class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
            <h3 class="font-medium text-yellow-800 mb-1">Drafts Pending</h3>
            <p class="text-2xl font-bold text-yellow-900">{{ gradingStats.drafts_pending }}</p>
            <p class="text-sm text-yellow-700 mt-1">Unfinished grades waiting to be submitted</p>
          </div>

          <!-- Top Performers -->
          <div class="bg-white rounded-lg shadow p-4">
            <h3 class="font-medium text-gray-900 mb-3">Top Performers</h3>
            <div v-if="topReps.length > 0" class="space-y-2">
              <div 
                v-for="(rep, index) in topReps" 
                :key="rep.rep_name"
                class="flex items-center justify-between"
              >
                <div class="flex items-center gap-2">
                  <span class="text-lg">{{ ['🥇', '🥈', '🥉', '4.', '5.'][index] }}</span>
                  <span class="text-sm text-gray-700">{{ rep.rep_name }}</span>
                </div>
                <span class="text-sm font-medium text-green-600">{{ Math.round(rep.avg_score) }}%</span>
              </div>
            </div>
            <p v-else class="text-sm text-gray-500">Need 3+ graded calls per rep</p>
          </div>

          <!-- Needs Coaching -->
          <div class="bg-white rounded-lg shadow p-4">
            <h3 class="font-medium text-gray-900 mb-3">Needs Coaching</h3>
            <div v-if="bottomReps.length > 0" class="space-y-2">
              <div 
                v-for="rep in bottomReps" 
                :key="rep.rep_name"
                class="flex items-center justify-between"
              >
                <span class="text-sm text-gray-700">{{ rep.rep_name }}</span>
                <span class="text-sm font-medium text-red-600">{{ Math.round(rep.avg_score) }}%</span>
              </div>
            </div>
            <p v-else class="text-sm text-gray-500">Need 3+ graded calls per rep</p>
          </div>

          <!-- Weakest Categories -->
          <div class="bg-white rounded-lg shadow p-4">
            <h3 class="font-medium text-gray-900 mb-3">Focus Areas</h3>
            <p class="text-xs text-gray-500 mb-2">Categories with lowest average scores</p>
            <div v-if="weakestCategories.length > 0" class="space-y-2">
              <div 
                v-for="cat in weakestCategories" 
                :key="cat.name"
                class="flex items-center justify-between"
              >
                <span class="text-sm text-gray-700">{{ cat.name }}</span>
                <span class="text-sm font-medium text-orange-600">{{ (cat.avg_score).toFixed(1) }}/4</span>
              </div>
            </div>
            <p v-else class="text-sm text-gray-500">Grade more calls to see insights</p>
            <Link 
              :href="route('manager.reports.category-breakdown')"
              class="block mt-3 text-sm text-blue-600 hover:text-blue-800"
            >
              View Category Report →
            </Link>
          </div>

          <!-- Notes Stats -->
          <div class="bg-white rounded-lg shadow p-4">
            <h3 class="font-medium text-gray-900 mb-3">Coaching Notes</h3>
            <div class="grid grid-cols-2 gap-4 text-center">
              <div>
                <p class="text-2xl font-bold text-gray-900">{{ notesStats.total_notes }}</p>
                <p class="text-xs text-gray-500">Total Notes</p>
              </div>
              <div>
                <p class="text-2xl font-bold text-gray-900">{{ notesStats.notes_this_week }}</p>
                <p class="text-xs text-gray-500">This Week</p>
              </div>
            </div>
            <div v-if="notesStats.objections_logged > 0" class="mt-3 pt-3 border-t">
              <p class="text-sm text-gray-600">
                Objection Success Rate: 
                <span class="font-medium" :class="notesStats.objections_overcame / notesStats.objections_logged > 0.5 ? 'text-green-600' : 'text-red-600'">
                  {{ Math.round((notesStats.objections_overcame / notesStats.objections_logged) * 100) }}%
                </span>
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </ManagerLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import ManagerLayout from '@/Layouts/ManagerLayout.vue';

const props = defineProps({
  gradingStats: Object,
  avgScore: Number,
  avgScoreThisWeek: Number,
  callsInQueue: Number,
  recentGrades: Array,
  activityByDay: Object,
  topReps: Array,
  bottomReps: Array,
  weakestCategories: Array,
  notesStats: Object,
});

function scoreColor(score) {
  if (score >= 85) return 'text-green-600';
  if (score >= 70) return 'text-blue-600';
  if (score >= 50) return 'text-orange-500';
  return 'text-red-600';
}

function formatDate(date) {
  return new Date(date).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
  });
}

// StatCard component inline
const StatCard = {
  props: ['title', 'value', 'icon', 'valueColor', 'link', 'linkText'],
  template: `
    <div class="bg-white rounded-lg shadow p-4">
      <p class="text-sm text-gray-500">{{ title }}</p>
      <p :class="['text-2xl font-bold', valueColor || 'text-gray-900']">{{ value }}</p>
      <a v-if="link" :href="link" class="text-xs text-blue-600 hover:text-blue-800 mt-1 inline-block">
        {{ linkText }}
      </a>
    </div>
  `,
};

// Simple Activity Chart component
const ActivityChart = {
  props: ['data'],
  template: `
    <div class="flex items-end justify-between h-full gap-1">
      <div 
        v-for="(count, date) in data" 
        :key="date"
        class="flex-1 flex flex-col items-center"
      >
        <div 
          class="w-full bg-blue-500 rounded-t transition-all"
          :style="{ height: getHeight(count) + '%', minHeight: count > 0 ? '4px' : '0' }"
        />
        <span class="text-xs text-gray-400 mt-1">{{ formatDay(date) }}</span>
        <span class="text-xs font-medium text-gray-600">{{ count }}</span>
      </div>
    </div>
  `,
  methods: {
    getHeight(count) {
      const max = Math.max(...Object.values(this.data), 1);
      return (count / max) * 80;
    },
    formatDay(date) {
      return new Date(date).toLocaleDateString('en-US', { weekday: 'short' }).slice(0, 2);
    },
  },
};
</script>
```

---

## Step 6: Rep Performance Report Page

Create `resources/js/Pages/Manager/Reports/RepPerformance.vue`:

```vue
<template>
  <ManagerLayout>
    <div class="max-w-7xl mx-auto px-4 py-6">
      <!-- Header -->
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Rep Performance Report</h1>
        <p class="text-gray-600">Compare scores across reps</p>
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

      <!-- Results Table -->
      <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rep</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Calls</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Avg Score</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Range</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Solid</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tentative</th>
              <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Backed-in</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <tr v-for="rep in repStats" :key="rep.rep_name" class="hover:bg-gray-50">
              <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ rep.rep_name }}</td>
              <td class="px-4 py-3 text-sm text-gray-600">{{ rep.call_count }}</td>
              <td class="px-4 py-3">
                <span :class="['text-sm font-medium', scoreColor(rep.avg_score)]">
                  {{ Math.round(rep.avg_score) }}%
                </span>
              </td>
              <td class="px-4 py-3 text-sm text-gray-600">
                {{ Math.round(rep.min_score) }}% – {{ Math.round(rep.max_score) }}%
              </td>
              <td class="px-4 py-3">
                <span class="text-sm text-green-600">{{ rep.solid_count }}</span>
              </td>
              <td class="px-4 py-3">
                <span class="text-sm text-yellow-600">{{ rep.tentative_count }}</span>
              </td>
              <td class="px-4 py-3">
                <span class="text-sm text-orange-600">{{ rep.backed_in_count }}</span>
              </td>
            </tr>
          </tbody>
        </table>

        <div v-if="repStats.length === 0" class="p-8 text-center text-gray-500">
          No graded calls in this date range.
        </div>
      </div>
    </div>
  </ManagerLayout>
</template>

<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import ManagerLayout from '@/Layouts/ManagerLayout.vue';

const props = defineProps({
  repStats: Array,
  trendData: Object,
  filters: Object,
});

const localFilters = ref({ ...props.filters });

function applyFilters() {
  router.get(route('manager.reports.rep-performance'), localFilters.value, { preserveState: true });
}

function scoreColor(score) {
  if (score >= 85) return 'text-green-600';
  if (score >= 70) return 'text-blue-600';
  if (score >= 50) return 'text-orange-500';
  return 'text-red-600';
}
</script>
```

---

## Step 7: Category Breakdown Report Page

Create `resources/js/Pages/Manager/Reports/CategoryBreakdown.vue`:

```vue
<template>
  <ManagerLayout>
    <div class="max-w-7xl mx-auto px-4 py-6">
      <!-- Header -->
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Category Breakdown</h1>
        <p class="text-gray-600">See which rubric categories are strongest and weakest</p>
      </div>

      <!-- Filters -->
      <div class="bg-white rounded-lg shadow p-4 mb-6">
        <div class="flex gap-4 items-end flex-wrap">
          <div>
            <label class="block text-sm text-gray-600 mb-1">From</label>
            <input type="date" v-model="localFilters.date_from" class="border rounded px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="block text-sm text-gray-600 mb-1">To</label>
            <input type="date" v-model="localFilters.date_to" class="border rounded px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="block text-sm text-gray-600 mb-1">Rep</label>
            <select v-model="localFilters.rep" class="border rounded px-3 py-2 text-sm">
              <option value="">All Reps</option>
              <option v-for="rep in filterOptions.reps" :key="rep" :value="rep">{{ rep }}</option>
            </select>
          </div>
          <button @click="applyFilters" class="bg-blue-600 text-white rounded px-4 py-2 text-sm hover:bg-blue-700">
            Apply
          </button>
        </div>
      </div>

      <!-- Category Cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div 
          v-for="cat in categoryStats" 
          :key="cat.id"
          class="bg-white rounded-lg shadow p-4"
        >
          <div class="flex items-center justify-between mb-3">
            <div>
              <h3 class="font-medium text-gray-900">{{ cat.name }}</h3>
              <p class="text-xs text-gray-500">{{ cat.weight }}% weight • {{ cat.sample_count }} scores</p>
            </div>
            <div :class="['text-2xl font-bold', scoreColor(cat.avg_score * 25)]">
              {{ cat.avg_score.toFixed(1) }}/4
            </div>
          </div>

          <!-- Score distribution bar -->
          <div class="flex h-4 rounded overflow-hidden">
            <div 
              class="bg-red-500" 
              :style="{ width: (cat.score_1_count / cat.sample_count * 100) + '%' }"
              :title="cat.score_1_count + ' scored 1'"
            />
            <div 
              class="bg-orange-500" 
              :style="{ width: (cat.score_2_count / cat.sample_count * 100) + '%' }"
              :title="cat.score_2_count + ' scored 2'"
            />
            <div 
              class="bg-blue-500" 
              :style="{ width: (cat.score_3_count / cat.sample_count * 100) + '%' }"
              :title="cat.score_3_count + ' scored 3'"
            />
            <div 
              class="bg-green-500" 
              :style="{ width: (cat.score_4_count / cat.sample_count * 100) + '%' }"
              :title="cat.score_4_count + ' scored 4'"
            />
          </div>

          <!-- Legend -->
          <div class="flex justify-between mt-2 text-xs text-gray-500">
            <span>1: {{ cat.score_1_count }}</span>
            <span>2: {{ cat.score_2_count }}</span>
            <span>3: {{ cat.score_3_count }}</span>
            <span>4: {{ cat.score_4_count }}</span>
          </div>
        </div>
      </div>

      <div v-if="categoryStats.length === 0" class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
        No graded calls in this date range.
      </div>
    </div>
  </ManagerLayout>
</template>

<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import ManagerLayout from '@/Layouts/ManagerLayout.vue';

const props = defineProps({
  categoryStats: Array,
  filters: Object,
  filterOptions: Object,
});

const localFilters = ref({ ...props.filters });

function applyFilters() {
  router.get(route('manager.reports.category-breakdown'), localFilters.value, { preserveState: true });
}

function scoreColor(score) {
  if (score >= 85) return 'text-green-600';
  if (score >= 70) return 'text-blue-600';
  if (score >= 50) return 'text-orange-500';
  return 'text-red-600';
}
</script>
```

---

## Step 8: Objection Analysis Report Page

Create `resources/js/Pages/Manager/Reports/ObjectionAnalysis.vue`:

```vue
<template>
  <ManagerLayout>
    <div class="max-w-7xl mx-auto px-4 py-6">
      <!-- Header -->
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Objection Analysis</h1>
        <p class="text-gray-600">Track objection types and success rates</p>
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

      <!-- Overall Stats -->
      <div class="grid grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-4 text-center">
          <p class="text-3xl font-bold text-gray-900">{{ overallStats.total }}</p>
          <p class="text-sm text-gray-500">Total Objections</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center">
          <p class="text-3xl font-bold text-green-600">{{ overallStats.overcame }}</p>
          <p class="text-sm text-gray-500">Overcame</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center">
          <p class="text-3xl font-bold text-red-600">{{ overallStats.failed }}</p>
          <p class="text-sm text-gray-500">Failed</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center">
          <p :class="['text-3xl font-bold', overallStats.success_rate >= 50 ? 'text-green-600' : 'text-red-600']">
            {{ overallStats.success_rate }}%
          </p>
          <p class="text-sm text-gray-500">Success Rate</p>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- By Objection Type -->
        <div class="bg-white rounded-lg shadow">
          <div class="px-4 py-3 border-b">
            <h3 class="font-medium text-gray-900">By Objection Type</h3>
          </div>
          <div class="p-4 space-y-3">
            <div v-for="obj in objectionStats" :key="obj.id">
              <div class="flex items-center justify-between mb-1">
                <span class="text-sm text-gray-700">{{ obj.name }}</span>
                <span class="text-sm text-gray-500">{{ obj.total }} total</span>
              </div>
              <div class="flex h-4 rounded overflow-hidden bg-gray-100">
                <div 
                  class="bg-green-500" 
                  :style="{ width: (obj.overcame / obj.total * 100) + '%' }"
                />
                <div 
                  class="bg-red-500" 
                  :style="{ width: (obj.failed / obj.total * 100) + '%' }"
                />
              </div>
              <div class="flex justify-between text-xs text-gray-500 mt-1">
                <span>{{ obj.success_rate }}% success</span>
                <span>{{ obj.overcame }} / {{ obj.failed }}</span>
              </div>
            </div>
            <div v-if="objectionStats.length === 0" class="text-center text-gray-500 py-4">
              No objections logged in this period.
            </div>
          </div>
        </div>

        <!-- By Rep -->
        <div class="bg-white rounded-lg shadow">
          <div class="px-4 py-3 border-b">
            <h3 class="font-medium text-gray-900">By Rep</h3>
          </div>
          <div class="p-4 space-y-3">
            <div v-for="rep in repObjectionStats" :key="rep.rep_name">
              <div class="flex items-center justify-between mb-1">
                <span class="text-sm text-gray-700">{{ rep.rep_name }}</span>
                <span class="text-sm text-gray-500">{{ rep.total }} objections</span>
              </div>
              <div class="flex h-4 rounded overflow-hidden bg-gray-100">
                <div 
                  class="bg-green-500" 
                  :style="{ width: (rep.overcame / rep.total * 100) + '%' }"
                />
                <div 
                  class="bg-red-500" 
                  :style="{ width: (rep.failed / rep.total * 100) + '%' }"
                />
              </div>
              <div class="flex justify-between text-xs text-gray-500 mt-1">
                <span>{{ rep.success_rate }}% success</span>
                <span>{{ rep.overcame }} / {{ rep.failed }}</span>
              </div>
            </div>
            <div v-if="repObjectionStats.length === 0" class="text-center text-gray-500 py-4">
              No objections logged in this period.
            </div>
          </div>
        </div>
      </div>
    </div>
  </ManagerLayout>
</template>

<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import ManagerLayout from '@/Layouts/ManagerLayout.vue';

const props = defineProps({
  objectionStats: Array,
  repObjectionStats: Array,
  overallStats: Object,
  filters: Object,
});

const localFilters = ref({ ...props.filters });

function applyFilters() {
  router.get(route('manager.reports.objection-analysis'), localFilters.value, { preserveState: true });
}
</script>
```

---

## Step 9: Grading Activity Report Page

Create `resources/js/Pages/Manager/Reports/GradingActivity.vue`:

```vue
<template>
  <ManagerLayout>
    <div class="max-w-7xl mx-auto px-4 py-6">
      <!-- Header -->
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">My Grading Activity</h1>
        <p class="text-gray-600">Track your grading volume and patterns</p>
      </div>

      <!-- Filters -->
      <div class="bg-white rounded-lg shadow p-4 mb-6">
        <div class="flex gap-4 items-end">
          <div>
            <label class="block text-sm text-gray-600 mb-1">Period</label>
            <select v-model="localFilters.period" class="border rounded px-3 py-2 text-sm">
              <option value="7">Last 7 days</option>
              <option value="30">Last 30 days</option>
              <option value="90">Last 90 days</option>
            </select>
          </div>
          <button @click="applyFilters" class="bg-blue-600 text-white rounded px-4 py-2 text-sm hover:bg-blue-700">
            Apply
          </button>
        </div>
      </div>

      <!-- Stats Cards -->
      <div class="grid grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-4 text-center">
          <p class="text-3xl font-bold text-gray-900">{{ stats.total_period }}</p>
          <p class="text-sm text-gray-500">Total Graded</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center">
          <p class="text-3xl font-bold text-blue-600">{{ stats.daily_average }}</p>
          <p class="text-sm text-gray-500">Daily Average</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center">
          <p class="text-3xl font-bold text-green-600">{{ stats.best_day }}</p>
          <p class="text-sm text-gray-500">Best Day</p>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center">
          <p class="text-3xl font-bold text-orange-600">{{ stats.streak }}</p>
          <p class="text-sm text-gray-500">Current Streak</p>
        </div>
      </div>

      <!-- Activity Chart -->
      <div class="bg-white rounded-lg shadow p-4 mb-6">
        <h3 class="font-medium text-gray-900 mb-4">Daily Activity</h3>
        <div class="h-64 flex items-end gap-1">
          <div 
            v-for="(count, date) in activityData" 
            :key="date"
            class="flex-1 flex flex-col items-center"
          >
            <div 
              class="w-full bg-blue-500 rounded-t transition-all hover:bg-blue-600"
              :style="{ height: getHeight(count) + '%', minHeight: count > 0 ? '4px' : '0' }"
              :title="date + ': ' + count + ' calls'"
            />
          </div>
        </div>
        <div class="flex justify-between mt-2 text-xs text-gray-400">
          <span>{{ Object.keys(activityData)[0] }}</span>
          <span>{{ Object.keys(activityData).slice(-1)[0] }}</span>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Hourly Distribution -->
        <div class="bg-white rounded-lg shadow p-4">
          <h3 class="font-medium text-gray-900 mb-4">Time of Day</h3>
          <div class="h-32 flex items-end gap-0.5">
            <div 
              v-for="hour in 24" 
              :key="hour"
              class="flex-1"
            >
              <div 
                class="w-full bg-purple-500 rounded-t"
                :style="{ height: getHourHeight(hour - 1) + '%', minHeight: (hourlyDistribution[hour - 1] || 0) > 0 ? '2px' : '0' }"
                :title="formatHour(hour - 1) + ': ' + (hourlyDistribution[hour - 1] || 0)"
              />
            </div>
          </div>
          <div class="flex justify-between mt-2 text-xs text-gray-400">
            <span>12am</span>
            <span>6am</span>
            <span>12pm</span>
            <span>6pm</span>
            <span>12am</span>
          </div>
        </div>

        <!-- Day of Week Distribution -->
        <div class="bg-white rounded-lg shadow p-4">
          <h3 class="font-medium text-gray-900 mb-4">Day of Week</h3>
          <div class="space-y-2">
            <div 
              v-for="(day, index) in ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']" 
              :key="day"
              class="flex items-center gap-2"
            >
              <span class="w-8 text-sm text-gray-600">{{ day }}</span>
              <div class="flex-1 h-6 bg-gray-100 rounded overflow-hidden">
                <div 
                  class="h-full bg-green-500 rounded"
                  :style="{ width: getDayWidth(index + 1) + '%' }"
                />
              </div>
              <span class="w-8 text-sm text-gray-600 text-right">{{ dayOfWeekDistribution[index + 1] || 0 }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </ManagerLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import ManagerLayout from '@/Layouts/ManagerLayout.vue';

const props = defineProps({
  activityData: Object,
  hourlyDistribution: Object,
  dayOfWeekDistribution: Object,
  stats: Object,
  filters: Object,
});

const localFilters = ref({ ...props.filters });

function applyFilters() {
  router.get(route('manager.reports.grading-activity'), localFilters.value, { preserveState: true });
}

const maxDaily = computed(() => Math.max(...Object.values(props.activityData), 1));
const maxHourly = computed(() => Math.max(...Object.values(props.hourlyDistribution), 1));
const maxDay = computed(() => Math.max(...Object.values(props.dayOfWeekDistribution), 1));

function getHeight(count) {
  return (count / maxDaily.value) * 90;
}

function getHourHeight(hour) {
  return ((props.hourlyDistribution[hour] || 0) / maxHourly.value) * 90;
}

function getDayWidth(day) {
  return ((props.dayOfWeekDistribution[day] || 0) / maxDay.value) * 100;
}

function formatHour(hour) {
  if (hour === 0) return '12am';
  if (hour === 12) return '12pm';
  return hour < 12 ? hour + 'am' : (hour - 12) + 'pm';
}
</script>
```

---

## Verification Checklist

After implementation:

**Navigation:**
- [ ] Dashboard link in nav works
- [ ] Reports dropdown shows all 4 reports
- [ ] Active state highlights correctly

**Dashboard:**
- [ ] Stats cards show correct numbers
- [ ] Activity chart shows last 7 days
- [ ] Recent grades list shows latest 5
- [ ] Top performers list shows reps with 3+ calls
- [ ] Needs coaching list shows lowest performers
- [ ] Focus areas shows weakest categories
- [ ] Notes stats show totals

**Rep Performance Report:**
- [ ] Date filter works
- [ ] Table shows all reps with grades
- [ ] Scores color-coded correctly
- [ ] Appointment quality columns populate

**Category Breakdown Report:**
- [ ] Date filter works
- [ ] Rep filter works
- [ ] All 8 categories display
- [ ] Score distribution bars accurate
- [ ] Average scores calculate correctly

**Objection Analysis Report:**
- [ ] Date filter works
- [ ] Overall stats accurate
- [ ] By type breakdown shows all types
- [ ] By rep breakdown shows all reps
- [ ] Success rate calculations correct

**Grading Activity Report:**
- [ ] Period selector works (7/30/90 days)
- [ ] Daily activity chart renders
- [ ] Hourly distribution shows pattern
- [ ] Day of week shows pattern
- [ ] Stats cards accurate

---

## Test Flow

1. Grade several calls across different reps
2. Visit `/manager/dashboard` → see populated stats
3. Check activity chart shows grading activity
4. Visit Rep Performance report → see rep comparison
5. Filter by date range → results update
6. Visit Category Breakdown → see all 8 categories
7. Filter by rep → see that rep's category scores
8. Visit Objection Analysis → see objection stats
9. Visit Grading Activity → see your patterns
10. Change period to 90 days → chart updates

---

## Notes

- All reports are manager-specific (only see your own data)
- Dashboard is the new landing page showing key metrics
- Reports use date range filters (default 30 days)
- Charts are simple CSS-based (no charting library needed)
- Streak counts consecutive days with at least 1 grade
