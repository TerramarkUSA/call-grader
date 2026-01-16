<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TranscriptionLog;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CostDashboardController extends Controller
{
    public function index(Request $request)
    {
        $dateFrom = $request->get('date_from', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $dateTo = $request->get('date_to', Carbon::now()->format('Y-m-d'));

        // Overall stats
        $overallStats = TranscriptionLog::where('success', true)
            ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->selectRaw('
                COUNT(*) as total_transcriptions,
                SUM(audio_duration_seconds) as total_seconds,
                SUM(cost) as total_cost,
                AVG(cost) as avg_cost_per_call
            ')
            ->first();

        // Daily costs
        $dailyCosts = TranscriptionLog::where('success', true)
            ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(cost) as cost'),
                DB::raw('SUM(audio_duration_seconds) as seconds')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date')
            ->toArray();

        // Fill in missing days
        $period = Carbon::parse($dateFrom)->daysUntil($dateTo);
        $dailyData = [];
        foreach ($period as $date) {
            $key = $date->format('Y-m-d');
            $dailyData[$key] = $dailyCosts[$key] ?? [
                'date' => $key,
                'count' => 0,
                'cost' => 0,
                'seconds' => 0,
            ];
        }

        // Cost by office
        $costByOffice = TranscriptionLog::where('transcription_logs.success', true)
            ->whereBetween('transcription_logs.created_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->join('calls', 'transcription_logs.call_id', '=', 'calls.id')
            ->join('accounts', 'calls.account_id', '=', 'accounts.id')
            ->select(
                'accounts.id',
                'accounts.name',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(transcription_logs.cost) as cost'),
                DB::raw('SUM(transcription_logs.audio_duration_seconds) as seconds')
            )
            ->groupBy('accounts.id', 'accounts.name')
            ->orderBy('cost', 'desc')
            ->get();

        // Cost by project
        $costByProject = TranscriptionLog::where('transcription_logs.success', true)
            ->whereBetween('transcription_logs.created_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->join('calls', 'transcription_logs.call_id', '=', 'calls.id')
            ->leftJoin('projects', 'calls.project_id', '=', 'projects.id')
            ->select(
                DB::raw('COALESCE(projects.name, "Unknown") as project_name'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(transcription_logs.cost) as cost'),
                DB::raw('SUM(transcription_logs.audio_duration_seconds) as seconds')
            )
            ->groupBy('projects.name')
            ->orderBy('cost', 'desc')
            ->limit(10)
            ->get();

        // Failed transcriptions
        $failedCount = TranscriptionLog::where('success', false)
            ->whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->count();

        // Month-over-month comparison
        $lastMonthStart = Carbon::parse($dateFrom)->subMonth()->format('Y-m-d');
        $lastMonthEnd = Carbon::parse($dateTo)->subMonth()->format('Y-m-d');

        $lastMonthStats = TranscriptionLog::where('success', true)
            ->whereBetween('created_at', [$lastMonthStart, $lastMonthEnd . ' 23:59:59'])
            ->selectRaw('SUM(cost) as total_cost, COUNT(*) as total_count')
            ->first();

        $costChange = 0;
        if ($lastMonthStats->total_cost > 0) {
            $costChange = round((($overallStats->total_cost - $lastMonthStats->total_cost) / $lastMonthStats->total_cost) * 100, 1);
        }

        return view('admin.costs.index', [
            'overallStats' => [
                'total_transcriptions' => (int) ($overallStats->total_transcriptions ?? 0),
                'total_minutes' => round(($overallStats->total_seconds ?? 0) / 60, 1),
                'total_cost' => round($overallStats->total_cost ?? 0, 2),
                'avg_cost_per_call' => round($overallStats->avg_cost_per_call ?? 0, 4),
                'cost_per_minute' => ($overallStats->total_seconds ?? 0) > 0
                    ? round(($overallStats->total_cost ?? 0) / (($overallStats->total_seconds ?? 0) / 60), 4)
                    : 0,
            ],
            'dailyData' => array_values($dailyData),
            'costByOffice' => $costByOffice,
            'costByProject' => $costByProject,
            'failedCount' => $failedCount,
            'comparison' => [
                'last_month_cost' => round($lastMonthStats->total_cost ?? 0, 2),
                'last_month_count' => (int) ($lastMonthStats->total_count ?? 0),
                'cost_change' => $costChange,
            ],
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
        ]);
    }
}
