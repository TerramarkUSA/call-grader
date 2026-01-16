<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Call;
use App\Models\Rep;
use App\Models\Project;
use Illuminate\Http\Request;
use Carbon\Carbon;

class CallQueueController extends Controller
{
    /**
     * Show the call queue
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        // Get user's accounts
        $accounts = $user->role === 'system_admin'
            ? Account::where('is_active', true)->get()
            : $user->accounts()->where('is_active', true)->get();

        if ($accounts->isEmpty()) {
            return view('manager.calls.no-account');
        }

        // Get selected account (default to first)
        $selectedAccountId = $request->get('account_id', $accounts->first()->id);
        $selectedAccount = $accounts->find($selectedAccountId) ?? $accounts->first();

        // Build query
        $query = Call::where('account_id', $selectedAccount->id)
            ->whereNull('ignored_at')
            ->where('call_quality', 'pending')
            ->with(['rep', 'project']);

        // Date filter (default: last 14 days for queue, up to 90 for search)
        $dateFilter = $request->get('date_filter', '14');
        $showingSearch = $request->has('search') || $request->get('date_filter') === 'all';

        if ($dateFilter === 'all') {
            $query->where('called_at', '>=', now()->subDays(90));
        } else {
            $query->where('called_at', '>=', now()->subDays((int) $dateFilter));
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('caller_name', 'like', "%{$search}%")
                  ->orWhere('caller_number', 'like', "%{$search}%");
            });
        }

        // Filter by rep
        if ($request->filled('rep_id')) {
            $query->where('rep_id', $request->get('rep_id'));
        }

        // Filter by project
        if ($request->filled('project_id')) {
            $query->where('project_id', $request->get('project_id'));
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('dial_status', $request->get('status'));
        }

        // Get calls
        $calls = $query->orderBy('called_at', 'desc')->paginate(25);

        // Calculate days old for visual aging
        $calls->getCollection()->transform(function ($call) {
            $call->days_old = $call->called_at->diffInDays(now());
            $call->is_expiring = $call->days_old >= 10 && $call->days_old < 14;
            $call->is_old = $call->days_old >= 7 && $call->days_old < 10;
            return $call;
        });

        // Get filter options
        $reps = Rep::where('account_id', $selectedAccount->id)->where('is_active', true)->orderBy('name')->get();
        $projects = Project::where('account_id', $selectedAccount->id)->where('is_active', true)->orderBy('name')->get();

        // Queue stats
        $stats = [
            'total_in_queue' => Call::where('account_id', $selectedAccount->id)
                ->whereNull('ignored_at')
                ->where('call_quality', 'pending')
                ->where('called_at', '>=', now()->subDays(14))
                ->count(),
            'expiring_soon' => Call::where('account_id', $selectedAccount->id)
                ->whereNull('ignored_at')
                ->where('call_quality', 'pending')
                ->whereBetween('called_at', [now()->subDays(14), now()->subDays(10)])
                ->count(),
        ];

        return view('manager.calls.index', compact(
            'accounts',
            'selectedAccount',
            'calls',
            'reps',
            'projects',
            'stats',
            'showingSearch'
        ));
    }

    /**
     * Ignore a call
     */
    public function ignore(Request $request, Call $call)
    {
        $request->validate([
            'reason' => 'nullable|string|max:255',
        ]);

        $call->update([
            'ignored_at' => now(),
            'ignore_reason' => $request->get('reason', 'Manually ignored'),
        ]);

        return back()->with('success', 'Call ignored.');
    }

    /**
     * Bulk ignore calls
     */
    public function bulkIgnore(Request $request)
    {
        $request->validate([
            'call_ids' => 'required|array',
            'call_ids.*' => 'exists:calls,id',
            'reason' => 'nullable|string|max:255',
        ]);

        Call::whereIn('id', $request->call_ids)
            ->whereNull('ignored_at')
            ->update([
                'ignored_at' => now(),
                'ignore_reason' => $request->get('reason', 'Bulk ignored'),
            ]);

        $count = count($request->call_ids);
        return back()->with('success', "{$count} calls ignored.");
    }

    /**
     * Mark call as bad
     */
    public function markBad(Request $request, Call $call)
    {
        $request->validate([
            'call_quality' => 'required|in:voicemail,wrong_number,no_conversation,test,spam,other',
            'call_quality_note' => 'nullable|string|max:255',
            'delete_recording' => 'boolean',
        ]);

        $call->update([
            'call_quality' => $request->call_quality,
            'call_quality_note' => $request->call_quality_note,
            'marked_bad_at' => now(),
            'marked_bad_by' => auth()->id(),
        ]);

        // Delete recording if requested
        if ($request->boolean('delete_recording') && $call->recording_path) {
            \Storage::delete($call->recording_path);
            $call->update(['recording_path' => null]);
        }

        return back()->with('success', 'Call marked as bad.');
    }

    /**
     * Bulk mark calls as bad
     */
    public function bulkMarkBad(Request $request)
    {
        $request->validate([
            'call_ids' => 'required|array',
            'call_ids.*' => 'exists:calls,id',
            'call_quality' => 'required|in:voicemail,wrong_number,no_conversation,test,spam,other',
        ]);

        Call::whereIn('id', $request->call_ids)
            ->where('call_quality', 'pending')
            ->update([
                'call_quality' => $request->call_quality,
                'marked_bad_at' => now(),
                'marked_bad_by' => auth()->id(),
            ]);

        $count = count($request->call_ids);
        return back()->with('success', "{$count} calls marked as bad.");
    }

    /**
     * Restore an ignored call
     */
    public function restore(Call $call)
    {
        $call->update([
            'ignored_at' => null,
            'ignore_reason' => null,
        ]);

        return back()->with('success', 'Call restored to queue.');
    }

    /**
     * Show processing page (pre-grading)
     */
    public function process(Call $call)
    {
        // Check for short call warning
        $showWarning = $call->talk_time < 30;

        return view('manager.calls.process', compact('call', 'showWarning'));
    }
}
