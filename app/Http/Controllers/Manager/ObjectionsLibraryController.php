<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\CoachingNote;
use App\Models\ObjectionType;
use App\Models\Rep;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ObjectionsLibraryController extends Controller
{
    public function index(Request $request)
    {
        $query = CoachingNote::where('author_id', Auth::id())
            ->where('is_objection', true)
            ->with([
                'objectionType:id,name',
                'category:id,name',
                'call:id,rep_id,project_id,called_at',
                'call.rep:id,name',
                'call.project:id,name',
            ])
            ->orderBy('created_at', 'desc');

        // Filters
        if ($request->filled('objection_type')) {
            $query->where('objection_type_id', $request->objection_type);
        }

        if ($request->filled('outcome')) {
            $query->where('objection_outcome', $request->outcome);
        }

        if ($request->filled('rep')) {
            $query->whereHas('call', fn($q) => $q->where('rep_id', $request->rep));
        }

        if ($request->filled('project')) {
            $query->whereHas('call', fn($q) => $q->where('project_id', $request->project));
        }

        $objections = $query->paginate(25)->withQueryString();

        // Get filter options
        $objectionTypes = ObjectionType::where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name']);

        $objectionCallIds = CoachingNote::where('author_id', Auth::id())
            ->where('is_objection', true)
            ->pluck('call_id');

        $repIds = DB::table('calls')
            ->whereIn('id', $objectionCallIds)
            ->pluck('rep_id')
            ->unique();

        $projectIds = DB::table('calls')
            ->whereIn('id', $objectionCallIds)
            ->pluck('project_id')
            ->unique();

        $reps = Rep::whereIn('id', $repIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        $projects = Project::whereIn('id', $projectIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        // Stats
        $totalObjections = CoachingNote::where('author_id', Auth::id())->where('is_objection', true)->count();
        $overcameCount = CoachingNote::where('author_id', Auth::id())->where('is_objection', true)->where('objection_outcome', 'overcame')->count();
        $failedCount = CoachingNote::where('author_id', Auth::id())->where('is_objection', true)->where('objection_outcome', 'failed')->count();

        // Stats by objection type
        $statsByType = CoachingNote::where('author_id', Auth::id())
            ->where('is_objection', true)
            ->select('objection_type_id', 'objection_outcome', DB::raw('count(*) as count'))
            ->groupBy('objection_type_id', 'objection_outcome')
            ->get()
            ->groupBy('objection_type_id')
            ->map(function($items) {
                return [
                    'overcame' => $items->where('objection_outcome', 'overcame')->sum('count'),
                    'failed' => $items->where('objection_outcome', 'failed')->sum('count'),
                ];
            });

        $stats = [
            'total' => $totalObjections,
            'overcame' => $overcameCount,
            'failed' => $failedCount,
            'by_type' => $statsByType,
        ];

        return view('manager.objections.index', compact(
            'objections',
            'objectionTypes',
            'reps',
            'projects',
            'stats'
        ));
    }
}
