<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Mail\CallFeedbackMail;
use App\Models\Account;
use App\Models\Grade;
use App\Models\Rep;
use App\Models\RubricCategory;
use App\Services\PerformanceStatsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class PerformanceController extends Controller
{
    public function __construct(
        protected PerformanceStatsService $statsService
    ) {}

    /**
     * Office-wide performance dashboard
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

        // Get selected account
        $selectedAccountId = $request->get('account_id', $accounts->first()->id);
        $selectedAccount = $accounts->find($selectedAccountId) ?? $accounts->first();

        // Parse date range
        $dateFilter = $request->get('date_filter', '30');
        $dateRange = $this->getDateRange($dateFilter, $request->get('date_start'), $request->get('date_end'));
        $startDate = $dateRange['start'];
        $endDate = $dateRange['end'];

        // Get all stats
        $summary = $this->statsService->getOfficeSummary($selectedAccount->id, $startDate, $endDate);
        $categoryAverages = $this->statsService->getOfficeCategoryAverages($selectedAccount->id, $startDate, $endDate);
        $scoreTrend = $this->statsService->getOfficeScoreTrend($selectedAccount->id, $startDate, $endDate);
        $repComparison = $this->statsService->getRepComparison($selectedAccount->id, $startDate, $endDate);

        // Get categories for table headers
        $categories = RubricCategory::where('is_active', true)->orderBy('sort_order')->get();

        // Date range label
        $dateRangeLabel = $this->getDateRangeLabel($dateFilter, $startDate, $endDate);

        return view('manager.performance.index', compact(
            'accounts',
            'selectedAccount',
            'dateFilter',
            'dateRangeLabel',
            'startDate',
            'endDate',
            'summary',
            'categoryAverages',
            'scoreTrend',
            'repComparison',
            'categories'
        ));
    }

    /**
     * Individual rep detail page
     */
    public function show(Request $request, Rep $rep)
    {
        $user = auth()->user();

        // Check user has access to this rep's account
        $account = $rep->account;
        if ($user->role !== 'system_admin' && !$user->accounts->contains($account->id)) {
            abort(403, 'You do not have access to this rep.');
        }

        // Parse date range
        $dateFilter = $request->get('date_filter', '30');
        $dateRange = $this->getDateRange($dateFilter, $request->get('date_start'), $request->get('date_end'));
        $startDate = $dateRange['start'];
        $endDate = $dateRange['end'];

        // Get rep stats
        $summary = $this->statsService->getRepSummary($rep->id, $account->id, $startDate, $endDate);
        $categoryAverages = $this->statsService->getRepCategoryAverages($rep->id, $account->id, $startDate, $endDate);
        $scoreTrend = $this->statsService->getRepScoreTrend($rep->id, $account->id, $startDate, $endDate);
        $recentCalls = $this->statsService->getRepRecentCalls($rep->id, 15);

        // Date range label
        $dateRangeLabel = $this->getDateRangeLabel($dateFilter, $startDate, $endDate);

        return view('manager.performance.show', compact(
            'rep',
            'account',
            'dateFilter',
            'dateRangeLabel',
            'startDate',
            'endDate',
            'summary',
            'categoryAverages',
            'scoreTrend',
            'recentCalls'
        ));
    }

    /**
     * Share all unshared feedback with rep
     */
    public function shareAll(Request $request, Rep $rep)
    {
        $user = auth()->user();

        // Check user has access to this rep's account
        $account = $rep->account;
        if ($user->role !== 'system_admin' && !$user->accounts->contains($account->id)) {
            abort(403, 'You do not have access to this rep.');
        }

        // Check rep has an email
        if (empty($rep->email)) {
            return back()->with('error', 'Rep does not have an email address configured.');
        }

        // Get unshared grades
        $unsharedGrades = Grade::whereHas('call', fn($q) => $q->where('rep_id', $rep->id))
            ->where('status', 'submitted')
            ->whereNull('shared_with_rep_at')
            ->with(['call.project', 'categoryScores.rubricCategory', 'checkpointResponses.rubricCheckpoint', 'coachingNotes.rubricCategory'])
            ->get();

        if ($unsharedGrades->isEmpty()) {
            return back()->with('info', 'No unshared feedback to send.');
        }

        $sentCount = 0;
        foreach ($unsharedGrades as $grade) {
            try {
                Mail::to($rep->email)->send(new CallFeedbackMail(
                    grade: $grade,
                    call: $grade->call,
                    manager: $user,
                    repName: $rep->name,
                    repEmail: $rep->email
                ));

                $grade->update([
                    'shared_with_rep_at' => now(),
                    'shared_with_rep_email' => $rep->email,
                    'shared_by_user_id' => $user->id,
                ]);

                $sentCount++;
            } catch (\Exception $e) {
                \Log::error("Failed to send feedback email for grade {$grade->id}: " . $e->getMessage());
            }
        }

        return back()->with('success', "Shared {$sentCount} feedback emails with {$rep->name}.");
    }

    /**
     * Get date range based on filter selection
     */
    protected function getDateRange(string $filter, ?string $customStart, ?string $customEnd): array
    {
        $end = now()->endOfDay();

        return match ($filter) {
            '7' => [
                'start' => now()->subDays(7)->startOfDay(),
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
            'this_month' => [
                'start' => now()->startOfMonth(),
                'end' => $end,
            ],
            'last_month' => [
                'start' => now()->subMonth()->startOfMonth(),
                'end' => now()->subMonth()->endOfMonth(),
            ],
            'custom' => [
                'start' => $customStart ? Carbon::parse($customStart)->startOfDay() : now()->subDays(30)->startOfDay(),
                'end' => $customEnd ? Carbon::parse($customEnd)->endOfDay() : $end,
            ],
            default => [
                'start' => now()->subDays(30)->startOfDay(),
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
            '7' => 'Last 7 days',
            '30' => 'Last 30 days',
            '90' => 'Last 90 days',
            'this_month' => 'This month',
            'last_month' => 'Last month',
            'custom' => $start->format('M j') . ' - ' . $end->format('M j, Y'),
            default => 'Last 30 days',
        };
    }
}
