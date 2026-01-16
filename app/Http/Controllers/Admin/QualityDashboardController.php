<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Grade;
use App\Models\User;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class QualityDashboardController extends Controller
{
    public function index(Request $request)
    {
        $dateFrom = $request->get('date_from', Carbon::now()->subDays(30)->format('Y-m-d'));
        $dateTo = $request->get('date_to', Carbon::now()->format('Y-m-d'));

        // Get thresholds from settings
        $flagThreshold = (int) Setting::get('grading_quality_flag_threshold', 25);
        $warnThreshold = (int) Setting::get('grading_quality_suspicious_threshold', 50);

        // Manager quality stats
        $managerStats = Grade::where('grades.status', 'submitted')
            ->whereNotNull('grades.grading_completed_at')
            ->whereBetween('grades.grading_completed_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->join('calls', 'grades.call_id', '=', 'calls.id')
            ->join('users', 'grades.graded_by', '=', 'users.id')
            ->select(
                'users.id as manager_id',
                'users.name as manager_name',
                DB::raw('COUNT(*) as total_grades'),
                DB::raw('AVG(grades.overall_score) as avg_score'),
                DB::raw('AVG(grades.playback_seconds) as avg_playback'),
                DB::raw('AVG(calls.talk_time) as avg_call_duration'),
                DB::raw("AVG(CASE WHEN calls.talk_time > 0 THEN (grades.playback_seconds / calls.talk_time) * 100 ELSE 0 END) as avg_playback_ratio"),
                DB::raw("SUM(CASE WHEN calls.talk_time > 0 AND (grades.playback_seconds / calls.talk_time) * 100 < {$flagThreshold} THEN 1 ELSE 0 END) as flagged_count"),
                DB::raw("SUM(CASE WHEN calls.talk_time > 0 AND (grades.playback_seconds / calls.talk_time) * 100 >= {$flagThreshold} AND (grades.playback_seconds / calls.talk_time) * 100 < {$warnThreshold} THEN 1 ELSE 0 END) as warned_count")
            )
            ->groupBy('users.id', 'users.name')
            ->orderBy('flagged_count', 'desc')
            ->get()
            ->map(function ($stat) {
                $stat->avg_playback_ratio = round($stat->avg_playback_ratio, 1);
                $stat->avg_score = round($stat->avg_score, 1);
                $stat->avg_playback_formatted = $stat->avg_playback > 0 ? gmdate('i:s', (int) $stat->avg_playback) : '00:00';
                $stat->avg_call_formatted = $stat->avg_call_duration > 0 ? gmdate('i:s', (int) $stat->avg_call_duration) : '00:00';
                return $stat;
            });

        // Overall quality stats
        $overallStats = Grade::where('grades.status', 'submitted')
            ->whereNotNull('grades.grading_completed_at')
            ->whereBetween('grades.grading_completed_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->join('calls', 'grades.call_id', '=', 'calls.id')
            ->selectRaw("
                COUNT(*) as total_grades,
                SUM(CASE WHEN calls.talk_time > 0 AND (grades.playback_seconds / calls.talk_time) * 100 < {$flagThreshold} THEN 1 ELSE 0 END) as flagged_count,
                SUM(CASE WHEN calls.talk_time > 0 AND (grades.playback_seconds / calls.talk_time) * 100 >= {$flagThreshold} AND (grades.playback_seconds / calls.talk_time) * 100 < {$warnThreshold} THEN 1 ELSE 0 END) as warned_count,
                AVG(CASE WHEN calls.talk_time > 0 THEN (grades.playback_seconds / calls.talk_time) * 100 ELSE 0 END) as avg_playback_ratio
            ")
            ->first();

        // Recent flagged grades
        $flaggedGrades = Grade::where('grades.status', 'submitted')
            ->whereNotNull('grades.grading_completed_at')
            ->whereBetween('grades.grading_completed_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->join('calls', 'grades.call_id', '=', 'calls.id')
            ->join('users', 'grades.graded_by', '=', 'users.id')
            ->leftJoin('reps', 'calls.rep_id', '=', 'reps.id')
            ->whereRaw("calls.talk_time > 0 AND (grades.playback_seconds / calls.talk_time) * 100 < {$flagThreshold}")
            ->select(
                'grades.id',
                'grades.call_id',
                'grades.overall_score',
                'grades.playback_seconds',
                'grades.grading_completed_at',
                'calls.talk_time',
                DB::raw('COALESCE(reps.name, "Unknown") as rep_name'),
                'users.name as manager_name',
                DB::raw('ROUND((grades.playback_seconds / calls.talk_time) * 100, 1) as playback_ratio')
            )
            ->orderBy('grades.grading_completed_at', 'desc')
            ->limit(20)
            ->get();

        $totalGrades = (int) $overallStats->total_grades;
        $flaggedCount = (int) $overallStats->flagged_count;
        $warnedCount = (int) $overallStats->warned_count;

        return view('admin.quality.index', [
            'managerStats' => $managerStats,
            'overallStats' => [
                'total_grades' => $totalGrades,
                'flagged_count' => $flaggedCount,
                'warned_count' => $warnedCount,
                'avg_playback_ratio' => round($overallStats->avg_playback_ratio ?? 0, 1),
                'quality_rate' => $totalGrades > 0
                    ? round((($totalGrades - $flaggedCount - $warnedCount) / $totalGrades) * 100, 1)
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
        $warnThreshold = (int) Setting::get('grading_quality_suspicious_threshold', 50);

        // All grades for this manager
        $grades = Grade::where('graded_by', $manager->id)
            ->where('grades.status', 'submitted')
            ->whereNotNull('grades.grading_completed_at')
            ->whereBetween('grades.grading_completed_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->join('calls', 'grades.call_id', '=', 'calls.id')
            ->leftJoin('reps', 'calls.rep_id', '=', 'reps.id')
            ->leftJoin('projects', 'calls.project_id', '=', 'projects.id')
            ->select(
                'grades.id',
                'grades.call_id',
                'grades.overall_score',
                'grades.playback_seconds',
                'grades.grading_completed_at',
                'calls.talk_time',
                DB::raw('COALESCE(reps.name, "Unknown") as rep_name'),
                DB::raw('COALESCE(projects.name, "Unknown") as project_name'),
                DB::raw('ROUND((grades.playback_seconds / NULLIF(calls.talk_time, 0)) * 100, 1) as playback_ratio')
            )
            ->orderBy('grades.grading_completed_at', 'desc')
            ->paginate(25)
            ->withQueryString();

        // Stats for this manager
        $stats = Grade::where('graded_by', $manager->id)
            ->where('status', 'submitted')
            ->whereNotNull('grading_completed_at')
            ->whereBetween('grading_completed_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->join('calls', 'grades.call_id', '=', 'calls.id')
            ->selectRaw("
                COUNT(*) as total_grades,
                AVG(grades.overall_score) as avg_score,
                AVG(CASE WHEN calls.talk_time > 0 THEN (grades.playback_seconds / calls.talk_time) * 100 ELSE 0 END) as avg_playback_ratio,
                SUM(CASE WHEN calls.talk_time > 0 AND (grades.playback_seconds / calls.talk_time) * 100 < {$flagThreshold} THEN 1 ELSE 0 END) as flagged_count,
                SUM(CASE WHEN calls.talk_time > 0 AND (grades.playback_seconds / calls.talk_time) * 100 >= {$flagThreshold} AND (grades.playback_seconds / calls.talk_time) * 100 < {$warnThreshold} THEN 1 ELSE 0 END) as warned_count
            ")
            ->first();

        // Daily activity
        $dailyActivity = Grade::where('graded_by', $manager->id)
            ->where('status', 'submitted')
            ->whereNotNull('grading_completed_at')
            ->whereBetween('grading_completed_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->select(DB::raw('DATE(grading_completed_at) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->pluck('count', 'date')
            ->toArray();

        return view('admin.quality.manager-detail', [
            'manager' => $manager,
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
        $warnThreshold = (int) Setting::get('grading_quality_suspicious_threshold', 50);

        $filter = $request->get('filter', 'flagged'); // flagged, warned, all
        $managerId = $request->get('manager');

        $query = Grade::where('grades.status', 'submitted')
            ->whereNotNull('grades.grading_completed_at')
            ->join('calls', 'grades.call_id', '=', 'calls.id')
            ->join('users', 'grades.graded_by', '=', 'users.id')
            ->leftJoin('reps', 'calls.rep_id', '=', 'reps.id')
            ->leftJoin('projects', 'calls.project_id', '=', 'projects.id')
            ->select(
                'grades.id',
                'grades.call_id',
                'grades.overall_score',
                'grades.playback_seconds',
                'grades.grading_completed_at',
                'calls.talk_time',
                'calls.called_at',
                DB::raw('COALESCE(reps.name, "Unknown") as rep_name'),
                DB::raw('COALESCE(projects.name, "Unknown") as project_name'),
                'users.id as manager_id',
                'users.name as manager_name',
                DB::raw('ROUND((grades.playback_seconds / NULLIF(calls.talk_time, 0)) * 100, 1) as playback_ratio')
            );

        if ($filter === 'flagged') {
            $query->whereRaw("calls.talk_time > 0 AND (grades.playback_seconds / calls.talk_time) * 100 < {$flagThreshold}");
        } elseif ($filter === 'warned') {
            $query->whereRaw("calls.talk_time > 0 AND (grades.playback_seconds / calls.talk_time) * 100 >= {$flagThreshold} AND (grades.playback_seconds / calls.talk_time) * 100 < {$warnThreshold}");
        }

        if ($managerId) {
            $query->where('grades.graded_by', $managerId);
        }

        $grades = $query->orderBy('grades.grading_completed_at', 'desc')
            ->paginate(50)
            ->withQueryString();

        $managers = User::whereIn('role', ['manager', 'site_admin'])
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.quality.audit', [
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
