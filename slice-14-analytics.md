# Slice 14: Reports > Call Analytics Page

## Overview

Dedicated analytics page for deep insights into call patterns and performance. Separate from the Call Queue (which focuses on grading workflow).

**URL:** `/manager/reports/call-analytics`

---

## Page Structure

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│ Call Analytics                                                                  │
│ Understand your call patterns and performance                                   │
│                                                                                 │
│ [Date Range ▼]  [Office ▼]  [Project ▼]  [Rep ▼]           [Export CSV]        │
└─────────────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────────────┐
│ ROW 1: Summary Stats (5 cards with period comparison)                           │
└─────────────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────┐ ┌─────────────────────────────────────┐
│ ROW 2A: Call Volume Trend (line chart)  │ │ ROW 2B: Call Type Breakdown (donut) │
└─────────────────────────────────────────┘ └─────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────────────┐
│ ROW 3: Peak Hours Heatmap                                                       │
└─────────────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────┐ ┌─────────────────────────────────────┐
│ ROW 4A: Calls by Project (table)        │ │ ROW 4B: Calls by Rep (table)        │
└─────────────────────────────────────────┘ └─────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────────────┐
│ ROW 5: Conversion Funnel (requires Salesforce data)                             │
└─────────────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────────────┐
│ ROW 6: Outcomes & Objections (requires graded calls)                            │
└─────────────────────────────────────────────────────────────────────────────────┘
```

---

## 1. CONTROLLER

Create app/Http/Controllers/Manager/CallAnalyticsController.php:

```php
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
        $current = $baseQuery()->whereBetween('call_date', [$startDate, $endDate]);
        $totalCalls = $current->count();
        $conversations = (clone $current)->where('talk_time', '>', 60)->count();
        $avgTalkTime = (clone $current)->where('talk_time', '>', 0)->avg('talk_time') ?? 0;
        $appointments = (clone $current)->where('sf_appointment_made', true)->count();
        $conversionRate = $conversations > 0 ? ($appointments / $conversations) * 100 : 0;

        // Previous period
        $previous = $baseQuery()->whereBetween('call_date', [$previousStart, $previousEnd]);
        $prevTotalCalls = $previous->count();
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
                ->whereBetween('call_date', [$startDate, $endDate])
                ->select(
                    DB::raw('DATE(call_date) as date'),
                    DB::raw('COUNT(*) as total'),
                    DB::raw('SUM(CASE WHEN talk_time > 60 THEN 1 ELSE 0 END) as conversations'),
                    DB::raw('SUM(CASE WHEN sf_appointment_made = 1 THEN 1 ELSE 0 END) as appointments')
                )
                ->groupBy(DB::raw('DATE(call_date)'))
                ->orderBy('date')
                ->get();
        } else {
            $results = $baseQuery()
                ->whereBetween('call_date', [$startDate, $endDate])
                ->select(
                    DB::raw('YEARWEEK(call_date) as week'),
                    DB::raw('MIN(DATE(call_date)) as date'),
                    DB::raw('COUNT(*) as total'),
                    DB::raw('SUM(CASE WHEN talk_time > 60 THEN 1 ELSE 0 END) as conversations'),
                    DB::raw('SUM(CASE WHEN sf_appointment_made = 1 THEN 1 ELSE 0 END) as appointments')
                )
                ->groupBy(DB::raw('YEARWEEK(call_date)'))
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
        $calls = $baseQuery()->whereBetween('call_date', [$startDate, $endDate])->get();
        
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
            ->whereBetween('call_date', [$startDate, $endDate])
            ->select(
                DB::raw('DAYOFWEEK(call_date) as day_of_week'),
                DB::raw('HOUR(call_date) as hour'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy(DB::raw('DAYOFWEEK(call_date)'), DB::raw('HOUR(call_date)'))
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
            ->whereBetween('call_date', [$startDate, $endDate])
            ->whereNotNull('project_id')
            ->select(
                'project_id',
                DB::raw('COUNT(*) as total_calls'),
                DB::raw('SUM(CASE WHEN talk_time > 60 THEN 1 ELSE 0 END) as conversations'),
                DB::raw('AVG(CASE WHEN talk_time > 0 THEN talk_time ELSE NULL END) as avg_duration'),
                DB::raw('SUM(CASE WHEN sf_appointment_made = 1 THEN 1 ELSE 0 END) as appointments')
            )
            ->groupBy('project_id')
            ->with('project:id,name')
            ->orderByDesc('total_calls')
            ->limit(10)
            ->get()
            ->map(fn($r) => [
                'project' => $r->project?->name ?? 'Unknown',
                'total_calls' => $r->total_calls,
                'conversations' => $r->conversations,
                'connect_rate' => $r->total_calls > 0 ? round(($r->conversations / $r->total_calls) * 100, 1) : 0,
                'avg_duration' => gmdate('i:s', $r->avg_duration ?? 0),
                'appointments' => $r->appointments,
            ])
            ->toArray();
    }

    protected function getByRep($baseQuery, $startDate, $endDate): array
    {
        return $baseQuery()
            ->whereBetween('call_date', [$startDate, $endDate])
            ->whereNotNull('rep_id')
            ->select(
                'rep_id',
                DB::raw('COUNT(*) as total_calls'),
                DB::raw('SUM(CASE WHEN talk_time > 60 THEN 1 ELSE 0 END) as conversations'),
                DB::raw('AVG(CASE WHEN talk_time > 0 THEN talk_time ELSE NULL END) as avg_duration'),
                DB::raw('SUM(CASE WHEN sf_appointment_made = 1 THEN 1 ELSE 0 END) as appointments')
            )
            ->groupBy('rep_id')
            ->with('rep:id,name')
            ->orderByDesc('total_calls')
            ->limit(10)
            ->get()
            ->map(fn($r) => [
                'rep' => $r->rep?->name ?? 'Unknown',
                'total_calls' => $r->total_calls,
                'conversations' => $r->conversations,
                'connect_rate' => $r->total_calls > 0 ? round(($r->conversations / $r->total_calls) * 100, 1) : 0,
                'avg_duration' => gmdate('i:s', $r->avg_duration ?? 0),
                'appointments' => $r->appointments,
            ])
            ->toArray();
    }

    protected function getConversionFunnel($baseQuery, $startDate, $endDate): array
    {
        $query = $baseQuery()->whereBetween('call_date', [$startDate, $endDate]);
        
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
            ->whereBetween('call_date', [$startDate, $endDate])
            ->pluck('id');

        $outcomes = Grade::whereIn('call_id', $callIds)
            ->where('status', 'submitted')
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
            ->whereBetween('call_date', [$startDate, $endDate])
            ->pluck('id');

        return CoachingNote::whereIn('call_id', $callIds)
            ->where('is_objection', true)
            ->whereNotNull('objection_type_id')
            ->select('objection_type_id', DB::raw('COUNT(*) as count'))
            ->groupBy('objection_type_id')
            ->with('objectionType:id,name')
            ->orderByDesc('count')
            ->limit(5)
            ->get()
            ->map(fn($r) => [
                'objection' => $r->objectionType?->name ?? 'Unknown',
                'count' => $r->count,
            ])
            ->toArray();
    }

    public function export(Request $request)
    {
        // Export logic for CSV - same filters as index
        // Returns downloadable CSV with call data
    }
}
```

---

## 2. ROUTES

Add to routes/manager.php:

```php
Route::get('/reports/call-analytics', [CallAnalyticsController::class, 'index'])
    ->name('reports.call-analytics');
Route::get('/reports/call-analytics/export', [CallAnalyticsController::class, 'export'])
    ->name('reports.call-analytics.export');
```

---

## 3. VUE PAGE

Create resources/js/Pages/Manager/Reports/CallAnalytics.vue:

```vue
<template>
  <ManagerLayout>
    <div class="max-w-[1600px] mx-auto px-8 py-6">
      <!-- Header -->
      <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Call Analytics</h1>
        <p class="text-gray-500">Understand your call patterns and performance</p>
      </div>

      <!-- Filters -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
        <div class="flex flex-wrap items-center gap-4">
          <div>
            <label class="block text-xs text-gray-500 mb-1">Date Range</label>
            <select v-model="localFilters.date_range" @change="applyFilters" class="border border-gray-200 rounded-lg px-3 py-2 text-sm">
              <option value="today">Today</option>
              <option value="yesterday">Yesterday</option>
              <option value="last_7_days">Last 7 Days</option>
              <option value="last_14_days">Last 14 Days</option>
              <option value="last_30_days">Last 30 Days</option>
              <option value="last_90_days">Last 90 Days</option>
              <option value="this_month">This Month</option>
              <option value="last_month">Last Month</option>
              <option value="custom">Custom</option>
            </select>
          </div>
          
          <div v-if="localFilters.date_range === 'custom'" class="flex gap-2">
            <input type="date" v-model="localFilters.start_date" @change="applyFilters" class="border border-gray-200 rounded-lg px-3 py-2 text-sm" />
            <input type="date" v-model="localFilters.end_date" @change="applyFilters" class="border border-gray-200 rounded-lg px-3 py-2 text-sm" />
          </div>

          <div v-if="filterOptions.accounts.length > 1">
            <label class="block text-xs text-gray-500 mb-1">Office</label>
            <select v-model="localFilters.account_id" @change="applyFilters" class="border border-gray-200 rounded-lg px-3 py-2 text-sm">
              <option value="">All Offices</option>
              <option v-for="a in filterOptions.accounts" :key="a.id" :value="a.id">{{ a.name }}</option>
            </select>
          </div>

          <div>
            <label class="block text-xs text-gray-500 mb-1">Project</label>
            <select v-model="localFilters.project_id" @change="applyFilters" class="border border-gray-200 rounded-lg px-3 py-2 text-sm">
              <option value="">All Projects</option>
              <option v-for="p in filterOptions.projects" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
          </div>

          <div>
            <label class="block text-xs text-gray-500 mb-1">Rep</label>
            <select v-model="localFilters.rep_id" @change="applyFilters" class="border border-gray-200 rounded-lg px-3 py-2 text-sm">
              <option value="">All Reps</option>
              <option v-for="r in filterOptions.reps" :key="r.id" :value="r.id">{{ r.name }}</option>
            </select>
          </div>

          <div class="ml-auto">
            <button @click="exportCsv" class="border border-gray-200 text-gray-600 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-50">
              Export CSV
            </button>
          </div>
        </div>
      </div>

      <!-- Row 1: Summary Stats -->
      <div class="grid grid-cols-5 gap-4 mb-6">
        <StatCard
          title="Total Calls"
          :value="summaryStats.total_calls.value"
          :change="summaryStats.total_calls.change"
        />
        <StatCard
          title="Connect Rate"
          :value="summaryStats.connect_rate.value + '%'"
          :change="summaryStats.connect_rate.change"
        />
        <StatCard
          title="Avg Talk Time"
          :value="summaryStats.avg_talk_time.formatted"
          :change="summaryStats.avg_talk_time.change"
        />
        <StatCard
          title="Appointments"
          :value="summaryStats.appointments.value"
          :change="summaryStats.appointments.change"
        />
        <StatCard
          title="Conversion Rate"
          :value="summaryStats.conversion_rate.value + '%'"
          :change="summaryStats.conversion_rate.change"
        />
      </div>

      <!-- Row 2: Volume Trend + Type Breakdown -->
      <div class="grid grid-cols-3 gap-6 mb-6">
        <div class="col-span-2 bg-white rounded-xl shadow-sm border border-gray-200 p-6">
          <h3 class="font-semibold text-gray-900 mb-4">Call Volume Trend</h3>
          <VolumeTrendChart :data="volumeTrend" />
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
          <h3 class="font-semibold text-gray-900 mb-4">Call Type Breakdown</h3>
          <TypeBreakdownChart :data="typeBreakdown" />
        </div>
      </div>

      <!-- Row 3: Peak Hours Heatmap -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
          <h3 class="font-semibold text-gray-900">Peak Call Hours</h3>
          <span v-if="peakHours.peak" class="text-sm text-gray-500">
            Busiest: <span class="font-medium text-gray-900">{{ peakHours.peak }}</span>
          </span>
        </div>
        <PeakHoursHeatmap :data="peakHours.data" :maxCount="peakHours.max_count" />
      </div>

      <!-- Row 4: By Project + By Rep -->
      <div class="grid grid-cols-2 gap-6 mb-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
          <h3 class="font-semibold text-gray-900 mb-4">Calls by Project</h3>
          <ProjectTable :data="byProject" />
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
          <h3 class="font-semibold text-gray-900 mb-4">Calls by Rep</h3>
          <RepTable :data="byRep" />
        </div>
      </div>

      <!-- Row 5: Conversion Funnel -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
        <h3 class="font-semibold text-gray-900 mb-4">Conversion Funnel</h3>
        <ConversionFunnel :data="conversionFunnel" />
      </div>

      <!-- Row 6: Outcomes + Objections -->
      <div class="grid grid-cols-2 gap-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
          <h3 class="font-semibold text-gray-900 mb-4">Call Outcomes</h3>
          <p class="text-sm text-gray-500 mb-4">From graded calls only</p>
          <OutcomesChart :data="outcomes" />
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
          <h3 class="font-semibold text-gray-900 mb-4">Top Objections</h3>
          <ObjectionsList :data="topObjections" />
        </div>
      </div>
    </div>
  </ManagerLayout>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import ManagerLayout from '@/Layouts/ManagerLayout.vue';
import StatCard from '@/Components/Analytics/StatCard.vue';
import VolumeTrendChart from '@/Components/Analytics/VolumeTrendChart.vue';
import TypeBreakdownChart from '@/Components/Analytics/TypeBreakdownChart.vue';
import PeakHoursHeatmap from '@/Components/Analytics/PeakHoursHeatmap.vue';
import ProjectTable from '@/Components/Analytics/ProjectTable.vue';
import RepTable from '@/Components/Analytics/RepTable.vue';
import ConversionFunnel from '@/Components/Analytics/ConversionFunnel.vue';
import OutcomesChart from '@/Components/Analytics/OutcomesChart.vue';
import ObjectionsList from '@/Components/Analytics/ObjectionsList.vue';

const props = defineProps({
  summaryStats: Object,
  volumeTrend: Array,
  typeBreakdown: Array,
  peakHours: Object,
  byProject: Array,
  byRep: Array,
  conversionFunnel: Array,
  outcomes: Array,
  topObjections: Array,
  filters: Object,
  filterOptions: Object,
});

const localFilters = reactive({ ...props.filters });

function applyFilters() {
  router.get(route('manager.reports.call-analytics'), localFilters, {
    preserveState: true,
    preserveScroll: true,
  });
}

function exportCsv() {
  window.location.href = route('manager.reports.call-analytics.export', localFilters);
}
</script>
```

---

## 4. COMPONENTS

### StatCard.vue

```vue
<template>
  <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
    <p class="text-sm text-gray-500 mb-1">{{ title }}</p>
    <div class="flex items-end justify-between">
      <span class="text-2xl font-bold text-gray-900">{{ value }}</span>
      <span 
        v-if="change !== null && change !== undefined"
        :class="[
          'text-sm font-medium flex items-center',
          change > 0 ? 'text-green-600' : change < 0 ? 'text-red-600' : 'text-gray-400'
        ]"
      >
        <svg v-if="change > 0" class="w-4 h-4 mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
        </svg>
        <svg v-else-if="change < 0" class="w-4 h-4 mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
        </svg>
        {{ Math.abs(change) }}%
      </span>
    </div>
    <p class="text-xs text-gray-400 mt-1">vs previous period</p>
  </div>
</template>

<script setup>
defineProps({
  title: String,
  value: [String, Number],
  change: Number,
});
</script>
```

### VolumeTrendChart.vue

```vue
<template>
  <div class="h-64">
    <canvas ref="chartCanvas"></canvas>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue';
import Chart from 'chart.js/auto';

const props = defineProps({
  data: Array,
});

const chartCanvas = ref(null);
let chart = null;

onMounted(() => {
  renderChart();
});

watch(() => props.data, () => {
  renderChart();
});

function renderChart() {
  if (chart) {
    chart.destroy();
  }

  const ctx = chartCanvas.value.getContext('2d');
  
  chart = new Chart(ctx, {
    type: 'line',
    data: {
      labels: props.data.map(d => formatDate(d.date)),
      datasets: [
        {
          label: 'Total Calls',
          data: props.data.map(d => d.total),
          borderColor: '#6b7280',
          backgroundColor: 'transparent',
          tension: 0.3,
        },
        {
          label: 'Conversations',
          data: props.data.map(d => d.conversations),
          borderColor: '#22c55e',
          backgroundColor: 'transparent',
          tension: 0.3,
        },
        {
          label: 'Appointments',
          data: props.data.map(d => d.appointments),
          borderColor: '#3b82f6',
          backgroundColor: 'transparent',
          tension: 0.3,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'bottom',
        },
      },
      scales: {
        y: {
          beginAtZero: true,
        },
      },
    },
  });
}

function formatDate(dateStr) {
  const date = new Date(dateStr);
  return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}
</script>
```

### TypeBreakdownChart.vue

```vue
<template>
  <div class="h-64">
    <canvas ref="chartCanvas"></canvas>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue';
import Chart from 'chart.js/auto';

const props = defineProps({
  data: Array,
});

const chartCanvas = ref(null);
let chart = null;

onMounted(() => {
  renderChart();
});

watch(() => props.data, () => {
  renderChart();
});

function renderChart() {
  if (chart) {
    chart.destroy();
  }

  const ctx = chartCanvas.value.getContext('2d');
  
  chart = new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels: props.data.map(d => d.label),
      datasets: [{
        data: props.data.map(d => d.count),
        backgroundColor: props.data.map(d => d.color),
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'right',
        },
      },
    },
  });
}
</script>
```

### PeakHoursHeatmap.vue

```vue
<template>
  <div class="overflow-x-auto">
    <table class="w-full text-xs">
      <thead>
        <tr>
          <th class="text-left py-2 text-gray-500 font-medium w-16"></th>
          <th v-for="day in days" :key="day.value" class="text-center py-2 text-gray-500 font-medium w-12">
            {{ day.label }}
          </th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="hour in hours" :key="hour">
          <td class="py-1 text-gray-500">{{ formatHour(hour) }}</td>
          <td 
            v-for="day in days" 
            :key="`${hour}-${day.value}`"
            class="p-1"
          >
            <div 
              class="w-full h-6 rounded"
              :style="{ backgroundColor: getCellColor(hour, day.value) }"
              :title="`${day.label} ${formatHour(hour)}: ${getCellCount(hour, day.value)} calls`"
            ></div>
          </td>
        </tr>
      </tbody>
    </table>
    <div class="flex items-center justify-end gap-4 mt-4 text-xs text-gray-500">
      <div class="flex items-center gap-1">
        <div class="w-4 h-4 rounded bg-gray-100"></div>
        <span>Low</span>
      </div>
      <div class="flex items-center gap-1">
        <div class="w-4 h-4 rounded bg-blue-300"></div>
        <span>Medium</span>
      </div>
      <div class="flex items-center gap-1">
        <div class="w-4 h-4 rounded bg-blue-600"></div>
        <span>High</span>
      </div>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  data: Array,
  maxCount: Number,
});

const days = [
  { value: 1, label: 'Sun' },
  { value: 2, label: 'Mon' },
  { value: 3, label: 'Tue' },
  { value: 4, label: 'Wed' },
  { value: 5, label: 'Thu' },
  { value: 6, label: 'Fri' },
  { value: 7, label: 'Sat' },
];

const hours = [8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18];

function formatHour(hour) {
  if (hour === 12) return '12pm';
  return hour < 12 ? `${hour}am` : `${hour - 12}pm`;
}

function getCellData(hour, day) {
  return props.data.find(d => d.hour === hour && d.day === day);
}

function getCellCount(hour, day) {
  return getCellData(hour, day)?.count ?? 0;
}

function getCellColor(hour, day) {
  const cell = getCellData(hour, day);
  if (!cell || cell.count === 0) return '#f3f4f6';
  
  const intensity = cell.intensity;
  if (intensity < 0.33) return '#dbeafe';
  if (intensity < 0.66) return '#93c5fd';
  return '#2563eb';
}
</script>
```

### ConversionFunnel.vue

```vue
<template>
  <div class="relative">
    <div class="flex items-end justify-between h-32">
      <div 
        v-for="(stage, index) in data" 
        :key="stage.stage"
        class="flex flex-col items-center flex-1"
      >
        <div 
          class="w-full bg-blue-500 rounded-t transition-all duration-500"
          :style="{ 
            height: `${Math.max(stage.percentage, 5)}%`,
            opacity: 1 - (index * 0.15)
          }"
        ></div>
      </div>
    </div>
    <div class="flex justify-between mt-4">
      <div 
        v-for="stage in data" 
        :key="stage.stage"
        class="flex-1 text-center"
      >
        <p class="text-sm font-medium text-gray-900">{{ stage.count }}</p>
        <p class="text-xs text-gray-500">{{ stage.stage }}</p>
        <p class="text-xs text-gray-400">{{ stage.percentage }}%</p>
      </div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  data: Array,
});
</script>
```

### ProjectTable.vue and RepTable.vue

```vue
<template>
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead>
        <tr class="border-b border-gray-100">
          <th class="text-left py-2 text-gray-500 font-medium">{{ labelColumn }}</th>
          <th class="text-right py-2 text-gray-500 font-medium">Calls</th>
          <th class="text-right py-2 text-gray-500 font-medium">Conv%</th>
          <th class="text-right py-2 text-gray-500 font-medium">Avg Dur</th>
          <th class="text-right py-2 text-gray-500 font-medium">Appts</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="row in data" :key="row[labelKey]" class="border-b border-gray-50 hover:bg-gray-50">
          <td class="py-3 font-medium text-gray-900">{{ row[labelKey] }}</td>
          <td class="py-3 text-right text-gray-600">{{ row.total_calls }}</td>
          <td class="py-3 text-right text-gray-600">{{ row.connect_rate }}%</td>
          <td class="py-3 text-right text-gray-600">{{ row.avg_duration }}</td>
          <td class="py-3 text-right text-gray-600">{{ row.appointments }}</td>
        </tr>
        <tr v-if="data.length === 0">
          <td colspan="5" class="py-8 text-center text-gray-400">No data available</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup>
defineProps({
  data: Array,
  labelColumn: { type: String, default: 'Name' },
  labelKey: { type: String, default: 'project' },
});
</script>
```

### OutcomesChart.vue

```vue
<template>
  <div class="space-y-3">
    <div v-for="outcome in data" :key="outcome.outcome" class="flex items-center gap-3">
      <div class="w-32 text-sm text-gray-600">{{ outcome.outcome }}</div>
      <div class="flex-1 h-6 bg-gray-100 rounded-full overflow-hidden">
        <div 
          class="h-full bg-blue-500 rounded-full transition-all duration-500"
          :style="{ width: `${getPercentage(outcome.count)}%` }"
        ></div>
      </div>
      <div class="w-12 text-sm text-gray-900 text-right">{{ outcome.count }}</div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  data: Array,
});

const maxCount = computed(() => Math.max(...props.data.map(d => d.count), 1));

function getPercentage(count) {
  return (count / maxCount.value) * 100;
}
</script>
```

### ObjectionsList.vue

```vue
<template>
  <div class="space-y-3">
    <div v-for="(obj, index) in data" :key="obj.objection" class="flex items-center gap-3">
      <span class="w-6 h-6 rounded-full bg-gray-100 text-gray-500 text-xs font-medium flex items-center justify-center">
        {{ index + 1 }}
      </span>
      <span class="flex-1 text-sm text-gray-700">{{ obj.objection }}</span>
      <span class="text-sm font-medium text-gray-900">{{ obj.count }}</span>
    </div>
    <div v-if="data.length === 0" class="text-center py-8 text-gray-400 text-sm">
      No objections recorded yet
    </div>
  </div>
</template>

<script setup>
defineProps({
  data: Array,
});
</script>
```

---

## 5. UPDATE NAVIGATION

Add to Reports dropdown in ManagerLayout:

```vue
<DropdownLink :href="route('manager.reports.call-analytics')">
  Call Analytics
</DropdownLink>
```

---

## 6. VERIFICATION CHECKLIST

After implementation:
- [ ] Page loads at /manager/reports/call-analytics
- [ ] Date range filter works (all presets + custom)
- [ ] Office/Project/Rep filters work
- [ ] Summary stats show with period comparison arrows
- [ ] Volume trend chart renders with 3 lines
- [ ] Type breakdown donut chart renders
- [ ] Peak hours heatmap shows (color intensity varies)
- [ ] By Project table shows top 10
- [ ] By Rep table shows top 10
- [ ] Conversion funnel shows (requires SF data)
- [ ] Outcomes chart shows (requires graded calls)
- [ ] Top objections list shows (requires coaching notes)
- [ ] Export CSV works
- [ ] Charts.js is installed (`npm install chart.js`)
- [ ] All filters preserve state on change

---

## 7. NOTES

- Chart.js required: `npm install chart.js`
- Some sections will be empty until:
  - Salesforce connected (funnel data)
  - Calls graded (outcomes data)
  - Coaching notes with objections added
- Heatmap uses business hours (8am-6pm)
- Period comparison looks at same-length previous period
