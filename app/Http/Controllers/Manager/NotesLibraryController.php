<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\CoachingNote;
use App\Models\Rep;
use App\Models\RubricCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotesLibraryController extends Controller
{
    public function index(Request $request)
    {
        $query = CoachingNote::where('author_id', Auth::id())
            ->with([
                'category:id,name',
                'objectionType:id,name',
                'call:id,rep_id,project_id,called_at',
                'call.rep:id,name',
                'call.project:id,name',
            ])
            ->orderBy('created_at', 'desc');

        // Filters
        if ($request->filled('category')) {
            if ($request->category === 'uncategorized') {
                $query->whereNull('rubric_category_id');
            } else {
                $query->where('rubric_category_id', $request->category);
            }
        }

        if ($request->filled('rep')) {
            $query->whereHas('call', fn($q) => $q->where('rep_id', $request->rep));
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('note_text', 'like', "%{$search}%")
                  ->orWhere('transcript_text', 'like', "%{$search}%");
            });
        }

        // Note type filter (overall, snippet, objection)
        if ($request->filled('note_type')) {
            switch ($request->note_type) {
                case 'overall':
                    $query->whereNull('line_index_start');
                    break;
                case 'snippet':
                    $query->whereNotNull('line_index_start')->where('is_objection', false);
                    break;
                case 'objection':
                    $query->where('is_objection', true);
                    break;
            }
        }

        $notes = $query->paginate(25)->withQueryString();

        // Get filter options
        $categories = RubricCategory::where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name']);

        $noteCallIds = CoachingNote::where('author_id', Auth::id())
            ->join('calls', 'coaching_notes.call_id', '=', 'calls.id')
            ->pluck('calls.rep_id')
            ->unique();

        $reps = Rep::whereIn('id', $noteCallIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        // Stats
        $stats = [
            'total_notes' => CoachingNote::where('author_id', Auth::id())->count(),
            'overall_notes' => CoachingNote::where('author_id', Auth::id())->whereNull('line_index_start')->count(),
            'objections' => CoachingNote::where('author_id', Auth::id())->where('is_objection', true)->count(),
        ];

        return view('manager.notes-library.index', compact(
            'notes',
            'categories',
            'reps',
            'stats'
        ));
    }
}
