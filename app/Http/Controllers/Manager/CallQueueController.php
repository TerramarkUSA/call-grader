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

        // Get selected account (persist selection in session)
        $defaultAccountId = session('manager_account_id', $accounts->first()->id);
        $selectedAccountId = $request->get('account_id', $defaultAccountId);
        $selectedAccount = $accounts->find($selectedAccountId) ?? $accounts->first();

        // Store selection in session for cross-page persistence
        session(['manager_account_id' => $selectedAccount->id]);

        // Build query
        $query = Call::where('account_id', $selectedAccount->id)
            ->whereNull('ignored_at')
            ->where('call_quality', 'pending')
            ->with(['rep', 'project', 'grades']);

        // Parse date filter
        $dateFilter = $request->get('date_filter', '14');
        $customStart = $request->get('date_start');
        $customEnd = $request->get('date_end');
        $showingSearch = $request->has('search');

        // Calculate date range based on filter
        $dateRange = $this->getDateRange($dateFilter, $customStart, $customEnd);
        $startDate = $dateRange['start'];
        $endDate = $dateRange['end'];

        // Apply date filter to main query
        $query->where('called_at', '>=', $startDate)
              ->where('called_at', '<=', $endDate);

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

        // Filter by display status (derived from dial_status + talk_time)
        if ($request->filled('display_status')) {
            $query->displayStatus($request->get('display_status'));
        }

        // Filter by grading status
        if ($request->filled('grading_status')) {
            $query->gradingStatus($request->get('grading_status'));
        }

        // Get calls - withQueryString preserves filters across pagination
        $calls = $query->orderBy('called_at', 'desc')->paginate(25)->withQueryString();

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

        // Base query for queue stats - uses same date range as main query
        $baseStatsQuery = Call::where('account_id', $selectedAccount->id)
            ->whereNull('ignored_at')
            ->where('call_quality', 'pending')
            ->where('called_at', '>=', $startDate)
            ->where('called_at', '<=', $endDate);

        // Queue stats with counts per display status
        $stats = [
            'total' => (clone $baseStatsQuery)->count(),
            'conversation' => (clone $baseStatsQuery)->displayStatus('conversation')->count(),
            'short_call' => (clone $baseStatsQuery)->displayStatus('short_call')->count(),
            'no_conversation' => (clone $baseStatsQuery)->displayStatus('no_conversation')->count(),
            'abandoned' => (clone $baseStatsQuery)->displayStatus('abandoned')->count(),
            'voicemail' => (clone $baseStatsQuery)->displayStatus('voicemail')->count(),
            'missed' => (clone $baseStatsQuery)->displayStatus('missed')->count(),
            'busy' => (clone $baseStatsQuery)->displayStatus('busy')->count(),
        ];

        // Grading status stats
        $gradingStats = [
            'needs_processing' => (clone $baseStatsQuery)->gradingStatus('needs_processing')->count(),
            'ready' => (clone $baseStatsQuery)->gradingStatus('ready')->count(),
            'in_progress' => (clone $baseStatsQuery)->gradingStatus('in_progress')->count(),
            'graded' => (clone $baseStatsQuery)->gradingStatus('graded')->count(),
        ];

        // Summary stats - within the selected date range
        $summaryStats = [
            'avg_duration' => (int) ((clone $baseStatsQuery)
                ->where('talk_time', '>', 0)
                ->avg('talk_time') ?? 0),
        ];

        // Date range label for display
        $dateRangeLabel = $this->getDateRangeLabel($dateFilter, $startDate, $endDate);

        // Display status options for filter
        $displayStatuses = Call::DISPLAY_STATUSES;

        // Grading status options for filter
        $gradingStatuses = Call::GRADING_STATUSES;

        return view('manager.calls.index', compact(
            'accounts',
            'selectedAccount',
            'calls',
            'reps',
            'projects',
            'stats',
            'gradingStats',
            'summaryStats',
            'showingSearch',
            'displayStatuses',
            'gradingStatuses',
            'dateFilter',
            'dateRangeLabel',
            'startDate',
            'endDate'
        ));
    }

    /**
     * Ignore a call
     */
    public function ignore(Request $request, Call $call)
    {
        $this->authorize('update', $call);

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

        $user = auth()->user();

        // Scope to user's accessible accounts
        $query = Call::whereIn('id', $request->call_ids)
            ->whereNull('ignored_at');

        if (!in_array($user->role, ['system_admin', 'site_admin'])) {
            $query->whereIn('account_id', $user->accounts->pluck('id'));
        }

        $count = $query->update([
            'ignored_at' => now(),
            'ignore_reason' => $request->get('reason', 'Bulk ignored'),
        ]);

        return back()->with('success', "{$count} calls ignored.");
    }

    /**
     * Mark call as bad
     */
    public function markBad(Request $request, Call $call)
    {
        $this->authorize('update', $call);

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

        $user = auth()->user();

        // Scope to user's accessible accounts
        $query = Call::whereIn('id', $request->call_ids)
            ->where('call_quality', 'pending');

        if (!in_array($user->role, ['system_admin', 'site_admin'])) {
            $query->whereIn('account_id', $user->accounts->pluck('id'));
        }

        $count = $query->update([
            'call_quality' => $request->call_quality,
            'marked_bad_at' => now(),
            'marked_bad_by' => auth()->id(),
        ]);

        return back()->with('success', "{$count} calls marked as bad.");
    }

    /**
     * Restore an ignored call
     */
    public function restore(Call $call)
    {
        $this->authorize('update', $call);

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
        $this->authorize('view', $call);

        // Check for short call warning
        $showWarning = $call->talk_time < 30;

        return view('manager.calls.process', compact('call', 'showWarning'));
    }

    /**
     * Get date range based on filter selection
     */
    protected function getDateRange(string $filter, ?string $customStart, ?string $customEnd): array
    {
        $end = now()->endOfDay();

        return match ($filter) {
            'today' => [
                'start' => now()->startOfDay(),
                'end' => $end,
            ],
            'yesterday' => [
                'start' => now()->subDay()->startOfDay(),
                'end' => now()->subDay()->endOfDay(),
            ],
            '7' => [
                'start' => now()->subDays(7)->startOfDay(),
                'end' => $end,
            ],
            '14' => [
                'start' => now()->subDays(14)->startOfDay(),
                'end' => $end,
            ],
            '30' => [
                'start' => now()->subDays(30)->startOfDay(),
                'end' => $end,
            ],
            '90' => [
                'start' => now()->subDays(90)->startOfDay(),
                'end' => $end,
            ],
            'custom' => [
                'start' => $customStart ? Carbon::parse($customStart)->startOfDay() : now()->subDays(14)->startOfDay(),
                'end' => $customEnd ? Carbon::parse($customEnd)->endOfDay() : $end,
            ],
            default => [
                'start' => now()->subDays(14)->startOfDay(),
                'end' => $end,
            ],
        };
    }

    /**
     * Get human-readable label for the date range
     */
    protected function getDateRangeLabel(string $filter, Carbon $start, Carbon $end): string
    {
        return match ($filter) {
            'today' => 'Today',
            'yesterday' => 'Yesterday',
            '7' => 'Last 7 days',
            '14' => 'Last 14 days',
            '30' => 'Last 30 days',
            '90' => 'Last 90 days',
            'custom' => $start->format('M j') . ' - ' . $end->format('M j, Y'),
            default => 'Last 14 days',
        };
    }
}
