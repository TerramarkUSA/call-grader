<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Call;
use App\Models\Grade;
use App\Models\Rep;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GradedCallsController extends Controller
{
    public function index(Request $request)
    {
        $query = Grade::where('graded_by', Auth::id())
            ->where('status', 'submitted')
            ->with(['call.rep', 'call.project'])
            ->orderBy('grading_completed_at', 'desc');

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

        // Get filter options based on calls the manager has graded
        $gradedCallIds = Grade::where('graded_by', Auth::id())->pluck('call_id');

        $reps = Rep::whereIn('id', Call::whereIn('id', $gradedCallIds)->pluck('rep_id'))
            ->orderBy('name')
            ->get(['id', 'name']);

        $projects = Project::whereIn('id', Call::whereIn('id', $gradedCallIds)->pluck('project_id'))
            ->orderBy('name')
            ->get(['id', 'name']);

        // Stats
        $stats = [
            'total_graded' => Grade::where('graded_by', Auth::id())->where('status', 'submitted')->count(),
            'avg_score' => round(Grade::where('graded_by', Auth::id())->where('status', 'submitted')->avg('overall_score') ?? 0, 2),
            'this_week' => Grade::where('graded_by', Auth::id())
                ->where('status', 'submitted')
                ->where('grading_completed_at', '>=', now()->startOfWeek())
                ->count(),
        ];

        return view('manager.graded-calls.index', compact(
            'grades',
            'reps',
            'projects',
            'stats'
        ));
    }
}
