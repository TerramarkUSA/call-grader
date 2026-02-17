<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CallInteraction;
use App\Models\CoachingNote;
use App\Models\Grade;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeaderboardController extends Controller
{
    public function index(Request $request)
    {
        $period = $request->get('period', 'week');

        $dateFrom = match ($period) {
            'today' => Carbon::today(),
            'yesterday' => Carbon::yesterday(),
            'week' => Carbon::now()->startOfWeek(),
            'month' => Carbon::now()->startOfMonth(),
            'quarter' => Carbon::now()->startOfQuarter(),
            default => Carbon::now()->subYears(10),
        };

        $dateTo = match ($period) {
            'yesterday' => Carbon::today(),
            default => null,
        };

        $flagThreshold = (int) Setting::get('grading_quality_flag_threshold', 25);

        // Grading metrics (feeds Tab 2: Grading Quality)
        $leaderboard = User::whereIn('role', ['manager', 'site_admin'])
            ->where('is_active', true)
            ->leftJoin('grades', function ($join) use ($dateFrom, $dateTo) {
                $join->on('users.id', '=', 'grades.graded_by')
                    ->where('grades.status', '=', 'submitted')
                    ->whereNotNull('grades.grading_completed_at')
                    ->where('grades.grading_completed_at', '>=', $dateFrom);
                if ($dateTo) {
                    $join->where('grades.grading_completed_at', '<', $dateTo);
                }
            })
            ->leftJoin('calls', 'grades.call_id', '=', 'calls.id')
            ->select(
                'users.id',
                'users.name',
                DB::raw('COUNT(grades.id) as grades_count'),
                DB::raw('AVG(grades.overall_score) as avg_score'),
                DB::raw("AVG(CASE WHEN calls.talk_time > 0 THEN (grades.playback_seconds / calls.talk_time) * 100 ELSE NULL END) as avg_playback_ratio"),
                DB::raw("SUM(CASE WHEN calls.talk_time > 0 AND (grades.playback_seconds / calls.talk_time) * 100 < {$flagThreshold} THEN 1 ELSE 0 END) as flagged_count"),
                DB::raw('SUM(grades.playback_seconds) as total_playback_seconds')
            )
            ->groupBy('users.id', 'users.name')
            ->orderBy('grades_count', 'desc')
            ->get()
            ->map(function ($user) {
                $user->avg_score = round($user->avg_score ?? 0, 1);
                $user->avg_playback_ratio = round($user->avg_playback_ratio ?? 0, 1);
                $user->quality_rate = $user->grades_count > 0
                    ? round((($user->grades_count - $user->flagged_count) / $user->grades_count) * 100, 1)
                    : 100;
                $user->total_playback_seconds = $user->total_playback_seconds ?? 0;
                return $user;
            });

        // Coaching notes count
        $notesQuery = CoachingNote::where('created_at', '>=', $dateFrom);
        if ($dateTo) {
            $notesQuery->where('created_at', '<', $dateTo);
        }
        $noteCounts = $notesQuery->select('author_id', DB::raw('COUNT(*) as count'))
            ->groupBy('author_id')
            ->pluck('count', 'author_id');

        // Activity metrics (feeds Tab 1: Activity Overview)
        $interactionsQuery = CallInteraction::where('created_at', '>=', $dateFrom);
        if ($dateTo) {
            $interactionsQuery->where('created_at', '<', $dateTo);
        }
        $interactions = $interactionsQuery->select(
            'user_id',
            DB::raw("SUM(CASE WHEN action = 'opened' THEN 1 ELSE 0 END) as opened_count"),
            DB::raw("SUM(CASE WHEN action = 'transcribed' THEN 1 ELSE 0 END) as transcribed_count"),
            DB::raw("SUM(CASE WHEN action = 'skipped' THEN 1 ELSE 0 END) as skipped_count"),
            DB::raw("SUM(CASE WHEN action = 'abandoned' THEN 1 ELSE 0 END) as abandoned_count"),
            DB::raw("COALESCE(SUM(page_seconds), 0) as total_page_seconds"),
            DB::raw("COALESCE(SUM(CASE WHEN action = 'skipped' THEN json_extract(metadata, '$.playback_seconds') ELSE 0 END), 0) as skipped_playback_seconds")
        )
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        // Merge all metrics into single collection
        $leaderboard = $leaderboard->map(function ($user) use ($noteCounts, $interactions) {
            $user->notes_count = $noteCounts[$user->id] ?? 0;

            $interaction = $interactions[$user->id] ?? null;
            $user->opened_count = $interaction->opened_count ?? 0;
            $user->transcribed_count = $interaction->transcribed_count ?? 0;
            $user->skipped_count = $interaction->skipped_count ?? 0;
            $user->abandoned_count = $interaction->abandoned_count ?? 0;
            $user->total_page_seconds = $interaction->total_page_seconds ?? 0;
            $user->skipped_playback_seconds = $interaction->skipped_playback_seconds ?? 0;

            // Completion % = graded / (graded + skipped + abandoned)
            $totalDecisions = $user->grades_count + $user->skipped_count + $user->abandoned_count;
            $user->completion_rate = $totalDecisions > 0
                ? round(($user->grades_count / $totalDecisions) * 100, 1)
                : 0;

            return $user;
        });

        // Rankings (3 cards)
        $byThorough = $leaderboard->filter(fn($u) => $u->transcribed_count >= 10)
            ->sortByDesc('completion_rate')->values();
        $byEfficient = $leaderboard->filter(fn($u) => $u->total_page_seconds > 0 && $u->grades_count >= 5)
            ->sortByDesc(fn($u) => $u->grades_count / ($u->total_page_seconds / 3600))
            ->values();
        $byScore = $leaderboard->where('grades_count', '>=', 5)->sortByDesc('avg_score')->values();

        // Static overall stats (4 cards, don't change between tabs)
        $totalProcessed = $leaderboard->sum('transcribed_count');
        $totalGrades = $leaderboard->sum('grades_count');
        $totalSkipped = $leaderboard->sum('skipped_count');
        $totalAbandoned = $leaderboard->sum('abandoned_count');
        $totalDecisionsAll = $totalGrades + $totalSkipped + $totalAbandoned;

        $overallStats = [
            'total_processed' => $totalProcessed,
            'total_grades' => $totalGrades,
            'avg_completion' => $totalDecisionsAll > 0
                ? round(($totalGrades / $totalDecisionsAll) * 100, 1)
                : 0,
            'avg_score_all' => round($leaderboard->where('grades_count', '>', 0)->avg('avg_score') ?? 0, 1),
        ];

        return view('admin.leaderboard.index', [
            'leaderboard' => $leaderboard->values(),
            'rankings' => [
                'byThorough' => $byThorough->take(5)->pluck('name', 'id')->toArray(),
                'byEfficient' => $byEfficient->take(5)->pluck('name', 'id')->toArray(),
                'byScore' => $byScore->take(5)->pluck('name', 'id')->toArray(),
            ],
            'overallStats' => $overallStats,
            'filters' => [
                'period' => $period,
            ],
        ]);
    }
}
