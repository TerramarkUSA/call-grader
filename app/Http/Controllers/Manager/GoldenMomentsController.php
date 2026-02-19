<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\CoachingNote;
use App\Models\Rep;
use App\Models\RubricCategory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GoldenMomentsController extends Controller
{
    public function index(Request $request)
    {
        $accountIds = Auth::user()->accounts->pluck('id');

        $query = CoachingNote::where('is_exemplar', true)
            ->whereHas('call', fn($q) => $q->whereIn('account_id', $accountIds))
            ->with([
                'author:id,name',
                'category:id,name',
                'objectionType:id,name',
                'call:id,rep_id,project_id,called_at',
                'call.rep:id,name',
                'call.project:id,name',
            ])
            ->orderBy('created_at', 'desc');

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

        if ($request->filled('author')) {
            $query->where('author_id', $request->author);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('note_text', 'like', "%{$search}%")
                  ->orWhere('transcript_text', 'like', "%{$search}%");
            });
        }

        $moments = $query->paginate(25)->withQueryString();

        $categories = RubricCategory::where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name']);

        // Reps from calls that have golden moments in these accounts
        $goldenCallIds = CoachingNote::where('is_exemplar', true)
            ->whereHas('call', fn($q) => $q->whereIn('account_id', $accountIds))
            ->pluck('call_id');

        $repIds = DB::table('calls')
            ->whereIn('id', $goldenCallIds)
            ->pluck('rep_id')
            ->unique();

        $reps = Rep::whereIn('id', $repIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        // Authors (managers) who have created golden moments in these accounts
        $authorIds = CoachingNote::where('is_exemplar', true)
            ->whereHas('call', fn($q) => $q->whereIn('account_id', $accountIds))
            ->pluck('author_id')
            ->unique();

        $authors = User::whereIn('id', $authorIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('manager.golden-moments.index', compact(
            'moments',
            'categories',
            'reps',
            'authors'
        ));
    }
}
