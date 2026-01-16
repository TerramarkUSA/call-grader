<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Grade;
use App\Models\CoachingNote;
use App\Models\User;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class LeaderboardController extends Controller
{
    public function index(Request $request)
    {
        $period = $request->get('period', 'week'); // week, month, quarter, all

        $dateFrom = match ($period) {
            'week' => Carbon::now()->startOfWeek(),
            'month' => Carbon::now()->startOfMonth(),
            'quarter' => Carbon::now()->startOfQuarter(),
            default => Carbon::now()->subYears(10),
        };

        $flagThreshold = (int) Setting::get('grading_quality_flag_threshold', 25);

        // Manager leaderboard
        $leaderboard = User::whereIn('role', ['manager', 'site_admin'])
            ->where('is_active', true)
            ->leftJoin('grades', function ($join) use ($dateFrom) {
                $join->on('users.id', '=', 'grades.graded_by')
                    ->where('grades.status', '=', 'submitted')
                    ->whereNotNull('grades.grading_completed_at')
                    ->where('grades.grading_completed_at', '>=', $dateFrom);
            })
            ->leftJoin('calls', 'grades.call_id', '=', 'calls.id')
            ->select(
                'users.id',
                'users.name',
                DB::raw('COUNT(grades.id) as grades_count'),
                DB::raw('AVG(grades.overall_score) as avg_score'),
                DB::raw("AVG(CASE WHEN calls.talk_time > 0 THEN (grades.playback_seconds / calls.talk_time) * 100 ELSE NULL END) as avg_playback_ratio"),
                DB::raw("SUM(CASE WHEN calls.talk_time > 0 AND (grades.playback_seconds / calls.talk_time) * 100 < {$flagThreshold} THEN 1 ELSE 0 END) as flagged_count")
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
                return $user;
            });

        // Add coaching notes count
        $noteCounts = CoachingNote::where('created_at', '>=', $dateFrom)
            ->select('author_id', DB::raw('COUNT(*) as count'))
            ->groupBy('author_id')
            ->pluck('count', 'author_id');

        $leaderboard = $leaderboard->map(function ($user) use ($noteCounts) {
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
            'avg_score_all' => $leaderboard->where('grades_count', '>', 0)->avg('avg_score') ?? 0,
        ];

        return view('admin.leaderboard.index', [
            'leaderboard' => $leaderboard->values(),
            'rankings' => [
                'byVolume' => $byVolume->take(5)->pluck('name', 'id')->toArray(),
                'byScore' => $byScore->take(5)->pluck('name', 'id')->toArray(),
                'byQuality' => $byQuality->take(5)->pluck('name', 'id')->toArray(),
                'byNotes' => $byNotes->take(5)->pluck('name', 'id')->toArray(),
            ],
            'overallStats' => $overallStats,
            'filters' => [
                'period' => $period,
            ],
        ]);
    }
}
