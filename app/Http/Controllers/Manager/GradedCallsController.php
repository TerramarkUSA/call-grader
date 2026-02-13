<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Call;
use App\Models\Grade;
use App\Models\Rep;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GradedCallsController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        // Get account IDs accessible to this user
        $accountIds = $user->role === 'system_admin'
            ? Account::where('is_active', true)->pluck('id')
            : $user->accounts()->where('is_active', true)->pluck('accounts.id');

        // Base query: all submitted grades for calls in accessible accounts
        $query = Grade::where('status', 'submitted')
            ->whereHas('call', fn($q) => $q->whereIn('account_id', $accountIds))
            ->with(['call.rep', 'call.project', 'gradedBy'])
            ->orderBy('grading_completed_at', 'desc');

        // Filter by grader
        if ($request->filled('grader')) {
            $query->where('graded_by', $request->grader);
        }

        // Filters
        if ($request->filled('rep')) {
            $query->whereHas('call', fn($q) => $q->where('rep_id', $request->rep));
        }

        if ($request->filled('project')) {
            $query->whereHas('call', fn($q) => $q->where('project_id', $request->project));
        }

        if ($request->filled('score_min')) {
            $query->where('overall_score', '>=', $request->score_min);
        }

        if ($request->filled('score_max')) {
            $query->where('overall_score', '<=', $request->score_max);
        }

        if ($request->filled('date_from')) {
            $query->where('grading_completed_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('grading_completed_at', '<=', $request->date_to . ' 23:59:59');
        }

        if ($request->filled('appointment_quality')) {
            $query->where('appointment_quality', $request->appointment_quality);
        }

        $grades = $query->paginate(25)->withQueryString();

        // Base scope for filter options & stats: all grades in accessible accounts
        $baseScope = Grade::where('status', 'submitted')
            ->whereHas('call', fn($q) => $q->whereIn('account_id', $accountIds));

        $gradedCallIds = (clone $baseScope)->pluck('call_id');

        $reps = Rep::whereIn('id', Call::whereIn('id', $gradedCallIds)->pluck('rep_id'))
            ->orderBy('name')
            ->get(['id', 'name']);

        $projects = Project::whereIn('id', Call::whereIn('id', $gradedCallIds)->pluck('project_id'))
            ->orderBy('name')
            ->get(['id', 'name']);

        // Get list of managers who have graded calls in these accounts
        $graders = User::whereIn('id', (clone $baseScope)->distinct()->pluck('graded_by'))
            ->orderBy('name')
            ->get(['id', 'name']);

        // Stats
        $stats = [
            'total_graded' => (clone $baseScope)->count(),
            'avg_score' => round((clone $baseScope)->avg('overall_score') ?? 0, 2),
            'this_week' => (clone $baseScope)
                ->where('grading_completed_at', '>=', now()->startOfWeek())
                ->count(),
        ];

        return view('manager.graded-calls.index', compact(
            'grades',
            'reps',
            'projects',
            'graders',
            'stats'
        ));
    }
}
