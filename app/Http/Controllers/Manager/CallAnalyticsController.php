<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Call;
use App\Models\Grade;
use App\Models\Rep;
use App\Models\Project;
use App\Models\CoachingNote;
use App\Models\ObjectionType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;

class CallAnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Get user's accessible accounts
        $accountIds = $user->role === 'system_admin'
            ? null // all accounts
            : $user->accounts->pluck('id')->toArray();

        // Filters
        $dateRange = $request->get('date_range', 'last_30_days');
        $dates = $this->getDateRange($dateRange, $request);
        $startDate = $dates['start'];
        $endDate = $dates['end'];
        $previousStart = $dates['previous_start'];
        $previousEnd = $dates['previous_end'];

        $accountId = $request->get('account_id');
        $projectId = $request->get('project_id');
        $repId = $request->get('rep_id');

        // Base query builder
        $baseQuery = function() use ($accountIds, $accountId, $projectId, $repId) {
            $query = Call::query();

            if ($accountIds) {
                $query->whereIn('account_id', $accountIds);
            }
            if ($accountId) {
                $query->where('account_id', $accountId);
            }
            if ($projectId) {
                $query->where('project_id', $projectId);
            }
            if ($repId) {
                $query->where('rep_id', $repId);
            }

            return $query;
        };

        // Summary Stats
        $summaryStats = $this->getSummaryStats($baseQuery, $startDate, $endDate, $previousStart, $previousEnd);

        // Call Volume Trend
        $volumeTrend = $this->getVolumeTrend($baseQuery, $startDate, $endDate);

        // Call Type Breakdown
        $typeBreakdown = $this->getTypeBreakdown($baseQuery, $startDate, $endDate);

        // Peak Hours Heatmap
        $peakHours = $this->getPeakHours($baseQuery, $startDate, $endDate);

        // By Project
        $byProject = $this->getByProject($baseQuery, $startDate, $endDate);

        // By Rep
        $byRep = $this->getByRep($baseQuery, $startDate, $endDate);

        // Conversion Funnel (if Salesforce data exists)
        $conversionFunnel = $this->getConversionFunnel($baseQuery, $startDate, $endDate);

        // Outcomes (graded calls)
        $outcomes = $this->getOutcomes($baseQuery, $startDate, $endDate);

        // Top Objections
        $topObjections = $this->getTopObjections($baseQuery, $startDate, $endDate);

        // Filter options
        $accounts = $user->role === 'system_admin'
            ? \App\Models\Account::where('is_active', true)->get(['id', 'name'])
            : $user->accounts()->where('is_active', true)->get(['id', 'name']);

        $projects = Project::when($accountId, fn($q) => $q->where('account_id', $accountId))
            ->when($accountIds, fn($q) => $q->whereIn('account_id', $accountIds))
            ->where('is_active', true)
            ->get(['id', 'name']);

        $reps = Rep::when($accountId, fn($q) => $q->where('account_id', $accountId))
            ->when($accountIds, fn($q) => $q->whereIn('account_id', $accountIds))
            ->where('is_active', true)
            ->get(['id', 'name']);

        return Inertia::render('Manager/Reports/CallAnalytics', [
            'summaryStats' => $summaryStats,
            'volumeTrend' => $volumeTrend,
            'typeBreakdown' => $typeBreakdown,
            'peakHours' => $peakHours,
            'byProject' => $byProject,
            'byRep' => $byRep,
            'conversionFunnel' => $conversionFunnel,
            'outcomes' => $outcomes,
            'topObjections' => $topObjections,
            'filters' => [
                'date_range' => $dateRange,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'account_id' => $accountId,
                'project_id' => $projectId,
                'rep_id' => $repId,
            ],
            'filterOptions' => [
                'accounts' => $accounts,
                'projects' => $projects,
                'reps' => $reps,
            ],
        ]);
    }

    protected function getDateRange(string $range, Request $request): array
    {
        $end = now()->endOfDay();

        switch ($range) {
            case 'today':
                $start = now()->startOfDay();
                $previousStart = now()->subDay()->startOfDay();
                $previousEnd = now()->subDay()->endOfDay();
                break;
            case 'yesterday':
                $start = now()->subDay()->startOfDay();
                $end = now()->subDay()->endOfDay();
                $previousStart = now()->subDays(2)->startOfDay();
                $previousEnd = now()->subDays(2)->endOfDay();
                break;
            case 'last_7_days':
                $start = now()->subDays(6)->startOfDay();
                $previousStart = now()->subDays(13)->startOfDay();
                $previousEnd = now()->subDays(7)->endOfDay();
                break;
            case 'last_14_days':
                $start = now()->subDays(13)->startOfDay();
                $previousStart = now()->subDays(27)->startOfDay();
                $previousEnd = now()->subDays(14)->endOfDay();
                break;
            case 'last_30_days':
                $start = now()->subDays(29)->startOfDay();
                $previousStart = now()->subDays(59)->startOfDay();
                $previousEnd = now()->subDays(30)->endOfDay();
                break;
            case 'last_90_days':
                $start = now()->subDays(89)->startOfDay();
                $previousStart = now()->subDays(179)->startOfDay();
                $previousEnd = now()->subDays(90)->endOfDay();
                break;
            case 'this_month':
                $start = now()->startOfMonth();
                $previousStart = now()->subMonth()->startOfMonth();
                $previousEnd = now()->subMonth()->endOfMonth();
                break;
            case 'last_month':
                $start = now()->subMonth()->startOfMonth();
                $end = now()->subMonth()->endOfMonth();
                $previousStart = now()->subMonths(2)->startOfMonth();
                $previousEnd = now()->subMonths(2)->endOfMonth();
                break;
            case 'custom':
                $start = Carbon::parse($request->get('start_date', now()->subDays(29)))->startOfDay();
                $end = Carbon::parse($request->get('end_date', now()))->endOfDay();
                $daysDiff = $start->diffInDays($end);
                $previousStart = $start->copy()->subDays($daysDiff + 1);
                $previousEnd = $start->copy()->subDay();
                break;
            default:
                $start = now()->subDays(29)->startOfDay();
                $previousStart = now()->subDays(59)->startOfDay();
                $previousEnd = now()->subDays(30)->endOfDay();
        }

        return [
            'start' => $start,
            'end' => $end,
            'previous_start' => $previousStart,
            'previous_end' => $previousEnd,
        ];
    }

    protected function getSummaryStats($baseQuery, $startDate, $endDate, $previousStart, $previousEnd): array
    {
        // Current period
        $current = $baseQuery()->whereBetween('called_at', [$startDate, $endDate]);
        $totalCalls = (clone $current)->count();
        $conversations = (clone $current)->where('talk_time', '>', 60)->count();
        $avgTalkTime = (clone $current)->where('talk_time', '>', 0)->avg('talk_time') ?? 0;
        $appointments = (clone $current)->where('sf_appointment_made', true)->count();
        $conversionRate = $conversations > 0 ? ($appointments / $conversations) * 100 : 0;

        // Previous period
        $previous = $baseQuery()->whereBetween('called_at', [$previousStart, $previousEnd]);
        $prevTotalCalls = (clone $previous)->count();
        $prevConversations = (clone $previous)->where('talk_time', '>', 60)->count();
        $prevAvgTalkTime = (clone $previous)->where('talk_time', '>', 0)->avg('talk_time') ?? 0;
        $prevAppointments = (clone $previous)->where('sf_appointment_made', true)->count();
        $prevConversionRate = $prevConversations > 0 ? ($prevAppointments / $prevConversations) * 100 : 0;

        // Calculate changes
        $calcChange = function($current, $previous) {
            if ($previous == 0) return $current > 0 ? 100 : 0;
            return round((($current - $previous) / $previous) * 100, 1);
        };

        return [
            'total_calls' => [
                'value' => $totalCalls,
                'change' => $calcChange($totalCalls, $prevTotalCalls),
            ],
            'connect_rate' => [
                'value' => $totalCalls > 0 ? round(($conversations / $totalCalls) * 100, 1) : 0,
                'change' => $calcChange(
                    $totalCalls > 0 ? ($conversations / $totalCalls) * 100 : 0,
                    $prevTotalCalls > 0 ? ($prevConversations / $prevTotalCalls) * 100 : 0
                ),
            ],
            'avg_talk_time' => [
                'value' => round($avgTalkTime),
                'formatted' => gmdate('i:s', $avgTalkTime),
                'change' => $calcChange($avgTalkTime, $prevAvgTalkTime),
            ],
            'appointments' => [
                'value' => $appointments,
                'change' => $calcChange($appointments, $prevAppointments),
            ],
            'conversion_rate' => [
                'value' => round($conversionRate, 1),
                'change' => $calcChange($conversionRate, $prevConversionRate),
            ],
        ];
    }

    protected function getVolumeTrend($baseQuery, $startDate, $endDate): array
    {
        $daysDiff = $startDate->diffInDays($endDate);

        // Group by day if <= 90 days, otherwise by week
        if ($daysDiff <= 90) {
            $results = $baseQuery()
                ->whereBetween('called_at', [$startDate, $endDate])
                ->select(
                    DB::raw('DATE(called_at) as date'),
                    DB::raw('COUNT(*) as total'),
                    DB::raw('SUM(CASE WHEN talk_time > 60 THEN 1 ELSE 0 END) as conversations'),
                    DB::raw('SUM(CASE WHEN sf_appointment_made = 1 THEN 1 ELSE 0 END) as appointments')
                )
                ->groupBy(DB::raw('DATE(called_at)'))
                ->orderBy('date')
                ->get();
        } else {
            $results = $baseQuery()
                ->whereBetween('called_at', [$startDate, $endDate])
                ->select(
                    DB::raw('YEARWEEK(called_at) as week'),
                    DB::raw('MIN(DATE(called_at)) as date'),
                    DB::raw('COUNT(*) as total'),
                    DB::raw('SUM(CASE WHEN talk_time > 60 THEN 1 ELSE 0 END) as conversations'),
                    DB::raw('SUM(CASE WHEN sf_appointment_made = 1 THEN 1 ELSE 0 END) as appointments')
                )
                ->groupBy(DB::raw('YEARWEEK(called_at)'))
                ->orderBy('week')
                ->get();
        }

        return $results->map(fn($r) => [
            'date' => $r->date,
            'total' => $r->total,
            'conversations' => $r->conversations,
            'appointments' => $r->appointments,
        ])->toArray();
    }

    protected function getTypeBreakdown($baseQuery, $startDate, $endDate): array
    {
        $calls = $baseQuery()->whereBetween('called_at', [$startDate, $endDate])->get();

        $breakdown = [
            'conversation' => 0,
            'short_call' => 0,
            'no_conversation' => 0,
            'abandoned' => 0,
            'voicemail' => 0,
            'missed' => 0,
        ];

        foreach ($calls as $call) {
            $status = $call->display_status;
            if (isset($breakdown[$status])) {
                $breakdown[$status]++;
            }
        }

        $total = array_sum($breakdown);

        return collect($breakdown)->map(function($count, $type) use ($total) {
            return [
                'type' => $type,
                'label' => $this->getTypeLabel($type),
                'count' => $count,
                'percentage' => $total > 0 ? round(($count / $total) * 100, 1) : 0,
                'color' => $this->getTypeColor($type),
            ];
        })->values()->toArray();
    }

    protected function getTypeLabel(string $type): string
    {
        return match($type) {
            'conversation' => 'Conversation',
            'short_call' => 'Short Call',
            'no_conversation' => 'No Conversation',
            'abandoned' => 'Abandoned',
            'voicemail' => 'Voicemail',
            'missed' => 'Missed',
            default => ucfirst($type),
        };
    }

    protected function getTypeColor(string $type): string
    {
        return match($type) {
            'conversation' => '#22c55e', // green
            'short_call' => '#eab308', // yellow
            'no_conversation' => '#ef4444', // red
            'abandoned' => '#9ca3af', // gray
            'voicemail' => '#a855f7', // purple
            'missed' => '#6b7280', // gray
            default => '#6b7280',
        };
    }

    protected function getPeakHours($baseQuery, $startDate, $endDate): array
    {
        $results = $baseQuery()
            ->whereBetween('called_at', [$startDate, $endDate])
            ->select(
                DB::raw('DAYOFWEEK(called_at) as day_of_week'),
                DB::raw('HOUR(called_at) as hour'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy(DB::raw('DAYOFWEEK(called_at)'), DB::raw('HOUR(called_at)'))
            ->get();

        // Initialize grid (hours 7-20, days 1-7)
        $grid = [];
        for ($hour = 7; $hour <= 20; $hour++) {
            $grid[$hour] = [];
            for ($day = 1; $day <= 7; $day++) {
                $grid[$hour][$day] = 0;
            }
        }

        // Fill in counts
        foreach ($results as $result) {
            $hour = (int)$result->hour;
            $day = (int)$result->day_of_week;
            if ($hour >= 7 && $hour <= 20) {
                $grid[$hour][$day] = $result->count;
            }
        }

        // Find max for intensity scaling
        $maxCount = max(array_map(fn($row) => max($row), $grid)) ?: 1;

        // Format for frontend
        $heatmapData = [];
        foreach ($grid as $hour => $days) {
            foreach ($days as $day => $count) {
                $heatmapData[] = [
                    'hour' => $hour,
                    'day' => $day,
                    'count' => $count,
                    'intensity' => $count / $maxCount,
                ];
            }
        }

        // Find peak
        $peak = collect($heatmapData)->sortByDesc('count')->first();
        $dayNames = ['', 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        $peakLabel = $peak ? "{$dayNames[$peak['day']]} {$peak['hour']}:00" : null;

        return [
            'data' => $heatmapData,
            'max_count' => $maxCount,
            'peak' => $peakLabel,
        ];
    }

    protected function getByProject($baseQuery, $startDate, $endDate): array
    {
        return $baseQuery()
            ->whereBetween('called_at', [$startDate, $endDate])
            ->whereNotNull('project_id')
            ->select(
                'project_id',
                DB::raw('COUNT(*) as total_calls'),
                DB::raw('SUM(CASE WHEN talk_time > 60 THEN 1 ELSE 0 END) as conversations'),
                DB::raw('AVG(CASE WHEN talk_time > 0 THEN talk_time ELSE NULL END) as avg_duration'),
                DB::raw('SUM(CASE WHEN sf_appointment_made = 1 THEN 1 ELSE 0 END) as appointments')
            )
            ->groupBy('project_id')
            ->orderByDesc('total_calls')
            ->limit(10)
            ->get()
            ->map(function($r) {
                $project = Project::find($r->project_id);
                return [
                    'project' => $project?->name ?? 'Unknown',
                    'total_calls' => $r->total_calls,
                    'conversations' => $r->conversations,
                    'connect_rate' => $r->total_calls > 0 ? round(($r->conversations / $r->total_calls) * 100, 1) : 0,
                    'avg_duration' => gmdate('i:s', $r->avg_duration ?? 0),
                    'appointments' => $r->appointments,
                ];
            })
            ->toArray();
    }

    protected function getByRep($baseQuery, $startDate, $endDate): array
    {
        return $baseQuery()
            ->whereBetween('called_at', [$startDate, $endDate])
            ->whereNotNull('rep_id')
            ->select(
                'rep_id',
                DB::raw('COUNT(*) as total_calls'),
                DB::raw('SUM(CASE WHEN talk_time > 60 THEN 1 ELSE 0 END) as conversations'),
                DB::raw('AVG(CASE WHEN talk_time > 0 THEN talk_time ELSE NULL END) as avg_duration'),
                DB::raw('SUM(CASE WHEN sf_appointment_made = 1 THEN 1 ELSE 0 END) as appointments')
            )
            ->groupBy('rep_id')
            ->orderByDesc('total_calls')
            ->limit(10)
            ->get()
            ->map(function($r) {
                $rep = Rep::find($r->rep_id);
                return [
                    'rep' => $rep?->name ?? 'Unknown',
                    'total_calls' => $r->total_calls,
                    'conversations' => $r->conversations,
                    'connect_rate' => $r->total_calls > 0 ? round(($r->conversations / $r->total_calls) * 100, 1) : 0,
                    'avg_duration' => gmdate('i:s', $r->avg_duration ?? 0),
                    'appointments' => $r->appointments,
                ];
            })
            ->toArray();
    }

    protected function getConversionFunnel($baseQuery, $startDate, $endDate): array
    {
        $query = $baseQuery()->whereBetween('called_at', [$startDate, $endDate]);

        $totalCalls = (clone $query)->count();
        $conversations = (clone $query)->where('talk_time', '>', 60)->count();
        $appointments = (clone $query)->where('sf_appointment_made', true)->count();
        $tours = (clone $query)->where('sf_toured_property', true)->count();
        $contracts = (clone $query)->where('sf_opportunity_created', true)->count();

        return [
            ['stage' => 'Calls', 'count' => $totalCalls, 'percentage' => 100],
            ['stage' => 'Conversations', 'count' => $conversations, 'percentage' => $totalCalls > 0 ? round(($conversations / $totalCalls) * 100, 1) : 0],
            ['stage' => 'Appointments', 'count' => $appointments, 'percentage' => $totalCalls > 0 ? round(($appointments / $totalCalls) * 100, 1) : 0],
            ['stage' => 'Tours', 'count' => $tours, 'percentage' => $totalCalls > 0 ? round(($tours / $totalCalls) * 100, 1) : 0],
            ['stage' => 'Contracts', 'count' => $contracts, 'percentage' => $totalCalls > 0 ? round(($contracts / $totalCalls) * 100, 1) : 0],
        ];
    }

    protected function getOutcomes($baseQuery, $startDate, $endDate): array
    {
        // Get graded calls with outcomes
        $callIds = $baseQuery()
            ->whereBetween('called_at', [$startDate, $endDate])
            ->pluck('id');

        $outcomes = Grade::whereIn('call_id', $callIds)
            ->where('status', 'submitted')
            ->whereNotNull('outcome')
            ->select('outcome', DB::raw('COUNT(*) as count'))
            ->groupBy('outcome')
            ->get()
            ->pluck('count', 'outcome')
            ->toArray();

        return [
            ['outcome' => 'Appointment Set', 'count' => $outcomes['appointment_set'] ?? 0],
            ['outcome' => 'Callback Scheduled', 'count' => $outcomes['callback'] ?? 0],
            ['outcome' => 'Not Qualified', 'count' => $outcomes['not_qualified'] ?? 0],
            ['outcome' => 'Not Interested', 'count' => $outcomes['no_appointment'] ?? 0],
            ['outcome' => 'Other', 'count' => $outcomes['other'] ?? 0],
        ];
    }

    protected function getTopObjections($baseQuery, $startDate, $endDate): array
    {
        $callIds = $baseQuery()
            ->whereBetween('called_at', [$startDate, $endDate])
            ->pluck('id');

        return CoachingNote::whereIn('call_id', $callIds)
            ->where('is_objection', true)
            ->whereNotNull('objection_type_id')
            ->select('objection_type_id', DB::raw('COUNT(*) as count'))
            ->groupBy('objection_type_id')
            ->orderByDesc('count')
            ->limit(5)
            ->get()
            ->map(function($r) {
                $objectionType = ObjectionType::find($r->objection_type_id);
                return [
                    'objection' => $objectionType?->name ?? 'Unknown',
                    'count' => $r->count,
                ];
            })
            ->toArray();
    }

    public function export(Request $request)
    {
        $user = $request->user();

        // Get user's accessible accounts
        $accountIds = $user->role === 'system_admin'
            ? null
            : $user->accounts->pluck('id')->toArray();

        // Filters
        $dateRange = $request->get('date_range', 'last_30_days');
        $dates = $this->getDateRange($dateRange, $request);
        $startDate = $dates['start'];
        $endDate = $dates['end'];

        $accountId = $request->get('account_id');
        $projectId = $request->get('project_id');
        $repId = $request->get('rep_id');

        // Build query
        $query = Call::query()
            ->with(['rep:id,name', 'project:id,name', 'account:id,name'])
            ->whereBetween('called_at', [$startDate, $endDate]);

        if ($accountIds) {
            $query->whereIn('account_id', $accountIds);
        }
        if ($accountId) {
            $query->where('account_id', $accountId);
        }
        if ($projectId) {
            $query->where('project_id', $projectId);
        }
        if ($repId) {
            $query->where('rep_id', $repId);
        }

        $calls = $query->orderBy('called_at', 'desc')->get();

        // Build CSV
        $headers = [
            'Date',
            'Time',
            'Caller Name',
            'Caller Number',
            'Rep',
            'Project',
            'Office',
            'Talk Time (sec)',
            'Status',
            'Appointment Made',
            'Toured',
            'Contract',
        ];

        $callback = function() use ($calls, $headers) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $headers);

            foreach ($calls as $call) {
                fputcsv($file, [
                    $call->called_at->format('Y-m-d'),
                    $call->called_at->format('H:i:s'),
                    $call->caller_name,
                    $call->caller_number,
                    $call->rep?->name ?? '',
                    $call->project?->name ?? '',
                    $call->account?->name ?? '',
                    $call->talk_time,
                    $call->display_status_label,
                    $call->sf_appointment_made ? 'Yes' : 'No',
                    $call->sf_toured_property ? 'Yes' : 'No',
                    $call->sf_opportunity_created ? 'Yes' : 'No',
                ]);
            }

            fclose($file);
        };

        $filename = 'call-analytics-' . $startDate->format('Y-m-d') . '-to-' . $endDate->format('Y-m-d') . '.csv';

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
