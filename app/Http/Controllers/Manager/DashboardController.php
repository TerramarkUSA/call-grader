<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Call;
use App\Models\Grade;
use App\Models\CoachingNote;
use App\Models\GradeCategoryScore;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
                ->where('grading_completed_at', '>=', $startOfWeek)
                ->count(),
            'graded_this_month' => Grade::where('graded_by', $userId)
                ->where('status', 'submitted')
                ->where('grading_completed_at', '>=', $startOfMonth)
                ->count(),
            'drafts_pending' => Grade::where('graded_by', $userId)->where('status', 'draft')->count(),
        ];

        // Average scores (overall_score is stored as 1-4 scale, convert to percentage)
        $avgScoreRaw = Grade::where('graded_by', $userId)
            ->where('status', 'submitted')
            ->avg('overall_score');
        $avgScore = $avgScoreRaw ? ($avgScoreRaw / 4) * 100 : 0;

        $avgScoreThisWeekRaw = Grade::where('graded_by', $userId)
            ->where('status', 'submitted')
            ->where('grading_completed_at', '>=', $startOfWeek)
            ->avg('overall_score');
        $avgScoreThisWeek = $avgScoreThisWeekRaw ? ($avgScoreThisWeekRaw / 4) * 100 : 0;

        // Calls in queue (ungraded)
        $callsInQueue = Call::whereDoesntHave('grades', function($q) use ($userId) {
                $q->where('graded_by', $userId);
            })
            ->whereNull('processed_at')
            ->whereNull('ignored_at')
            ->where('call_quality', 'pending')
            ->count();

        // Recent graded calls (last 5)
        $recentGrades = Grade::where('graded_by', $userId)
            ->where('status', 'submitted')
            ->with(['call.rep', 'call.project'])
            ->orderBy('grading_completed_at', 'desc')
            ->limit(5)
            ->get();

        // Grading activity last 7 days
        $activityByDay = Grade::where('graded_by', $userId)
            ->where('status', 'submitted')
            ->where('grading_completed_at', '>=', $now->copy()->subDays(7))
            ->select(DB::raw('DATE(grading_completed_at) as date'), DB::raw('COUNT(*) as count'))
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
        // overall_score is 1-4 scale, convert to percentage
        $topReps = Grade::where('graded_by', $userId)
            ->where('status', 'submitted')
            ->join('calls', 'grades.call_id', '=', 'calls.id')
            ->join('reps', 'calls.rep_id', '=', 'reps.id')
            ->select('reps.name as rep_name', DB::raw('(AVG(overall_score) / 4) * 100 as avg_score'), DB::raw('COUNT(*) as call_count'))
            ->groupBy('reps.id', 'reps.name')
            ->having('call_count', '>=', 3)
            ->orderBy('avg_score', 'desc')
            ->limit(5)
            ->get();

        // Bottom reps (need coaching)
        $bottomReps = Grade::where('graded_by', $userId)
            ->where('status', 'submitted')
            ->join('calls', 'grades.call_id', '=', 'calls.id')
            ->join('reps', 'calls.rep_id', '=', 'reps.id')
            ->select('reps.name as rep_name', DB::raw('(AVG(overall_score) / 4) * 100 as avg_score'), DB::raw('COUNT(*) as call_count'))
            ->groupBy('reps.id', 'reps.name')
            ->having('call_count', '>=', 3)
            ->orderBy('avg_score', 'asc')
            ->limit(5)
            ->get();

        // Weakest categories (lowest average scores)
        $weakestCategories = GradeCategoryScore::whereHas('grade', function($q) use ($userId) {
                $q->where('graded_by', $userId)->where('status', 'submitted');
            })
            ->join('rubric_categories', 'grade_category_scores.rubric_category_id', '=', 'rubric_categories.id')
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

        // Grading leaderboard — today, yesterday, this week
        $yesterday = Carbon::yesterday();
        $today = Carbon::today();

        $gradingLeaderboard = Grade::where('status', 'submitted')
            ->where('grading_completed_at', '>=', $startOfWeek)
            ->join('users', 'grades.graded_by', '=', 'users.id')
            ->select(
                'users.id',
                'users.name',
                DB::raw("SUM(CASE WHEN DATE(grading_completed_at) = '{$today->toDateString()}' THEN 1 ELSE 0 END) as today_count"),
                DB::raw("SUM(CASE WHEN DATE(grading_completed_at) = '{$yesterday->toDateString()}' THEN 1 ELSE 0 END) as yesterday_count"),
                DB::raw('COUNT(*) as week_count')
            )
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('today_count')
            ->orderByDesc('week_count')
            ->get();

        return view('manager.dashboard.index', [
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
            'gradingLeaderboard' => $gradingLeaderboard,
        ]);
    }
}
