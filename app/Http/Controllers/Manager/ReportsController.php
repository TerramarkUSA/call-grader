<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Grade;
use App\Models\GradeCategoryScore;
use App\Models\CoachingNote;
use App\Models\Rep;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
            ->whereBetween('grading_completed_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->join('calls', 'grades.call_id', '=', 'calls.id')
            ->join('reps', 'calls.rep_id', '=', 'reps.id')
            ->select(
                'reps.name as rep_name',
                DB::raw('COUNT(*) as call_count'),
                DB::raw('AVG((overall_score / 4) * 100) as avg_score'),
                DB::raw('MIN((overall_score / 4) * 100) as min_score'),
                DB::raw('MAX((overall_score / 4) * 100) as max_score'),
                DB::raw('SUM(CASE WHEN appointment_quality = "solid" THEN 1 ELSE 0 END) as solid_count'),
                DB::raw('SUM(CASE WHEN appointment_quality = "tentative" THEN 1 ELSE 0 END) as tentative_count'),
                DB::raw('SUM(CASE WHEN appointment_quality = "backed_in" THEN 1 ELSE 0 END) as backed_in_count')
            )
            ->groupBy('reps.id', 'reps.name')
            ->orderBy('avg_score', 'desc')
            ->get();

        return view('manager.reports.rep-performance', [
            'repStats' => $repStats,
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
              ->whereBetween('grading_completed_at', [$dateFrom, $dateTo . ' 23:59:59']);
        });

        if ($repFilter) {
            $query->whereHas('grade.call', fn($q) => $q->where('rep_id', $repFilter));
        }

        $categoryStats = $query
            ->join('rubric_categories', 'grade_category_scores.rubric_category_id', '=', 'rubric_categories.id')
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
        $reps = Rep::whereHas('calls.grades', function($q) use ($userId) {
            $q->where('graded_by', $userId)->where('status', 'submitted');
        })->orderBy('name')->get();

        return view('manager.reports.category-breakdown', [
            'categoryStats' => $categoryStats,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'rep' => $repFilter,
            ],
            'reps' => $reps,
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
        $objectionStats = CoachingNote::where('coaching_notes.author_id', $userId)
            ->where('coaching_notes.is_objection', true)
            ->whereBetween('coaching_notes.created_at', [$dateFrom, $dateTo . ' 23:59:59'])
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
        $repObjectionStats = CoachingNote::where('coaching_notes.author_id', $userId)
            ->where('coaching_notes.is_objection', true)
            ->whereBetween('coaching_notes.created_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->join('calls', 'coaching_notes.call_id', '=', 'calls.id')
            ->join('reps', 'calls.rep_id', '=', 'reps.id')
            ->select(
                'reps.name as rep_name',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN objection_outcome = "overcame" THEN 1 ELSE 0 END) as overcame'),
                DB::raw('SUM(CASE WHEN objection_outcome = "failed" THEN 1 ELSE 0 END) as failed')
            )
            ->groupBy('reps.id', 'reps.name')
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
            'total' => CoachingNote::where('coaching_notes.author_id', $userId)
                ->where('coaching_notes.is_objection', true)
                ->whereBetween('coaching_notes.created_at', [$dateFrom, $dateTo . ' 23:59:59'])
                ->count(),
            'overcame' => CoachingNote::where('coaching_notes.author_id', $userId)
                ->where('coaching_notes.is_objection', true)
                ->where('coaching_notes.objection_outcome', 'overcame')
                ->whereBetween('coaching_notes.created_at', [$dateFrom, $dateTo . ' 23:59:59'])
                ->count(),
            'failed' => CoachingNote::where('coaching_notes.author_id', $userId)
                ->where('coaching_notes.is_objection', true)
                ->where('coaching_notes.objection_outcome', 'failed')
                ->whereBetween('coaching_notes.created_at', [$dateFrom, $dateTo . ' 23:59:59'])
                ->count(),
        ];
        $overallStats['success_rate'] = $overallStats['total'] > 0
            ? round(($overallStats['overcame'] / $overallStats['total']) * 100, 1)
            : 0;

        return view('manager.reports.objection-analysis', [
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
            ->where('grading_completed_at', '>=', $startDate)
            ->select(DB::raw('DATE(grading_completed_at) as date'), DB::raw('COUNT(*) as count'))
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
            'daily_average' => count($activityData) > 0 ? round(array_sum($activityData) / count($activityData), 1) : 0,
            'best_day' => count($activityData) > 0 ? max($activityData) : 0,
            'streak' => $this->calculateStreak($activityData),
        ];

        // Hourly distribution (what time do they grade?)
        $hourlyDistribution = Grade::where('graded_by', $userId)
            ->where('status', 'submitted')
            ->where('grading_completed_at', '>=', $startDate)
            ->select(DB::raw('HOUR(grading_completed_at) as hour'), DB::raw('COUNT(*) as count'))
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->pluck('count', 'hour')
            ->toArray();

        // Day of week distribution
        $dayOfWeekDistribution = Grade::where('graded_by', $userId)
            ->where('status', 'submitted')
            ->where('grading_completed_at', '>=', $startDate)
            ->select(DB::raw('DAYOFWEEK(grading_completed_at) as day'), DB::raw('COUNT(*) as count'))
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->pluck('count', 'day')
            ->toArray();

        return view('manager.reports.grading-activity', [
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
