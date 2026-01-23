<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Call;
use App\Models\Grade;
use App\Models\GradeCategoryScore;
use App\Models\Rep;
use App\Models\RubricCategory;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PerformanceStatsService
{
    /**
     * Get office-wide summary stats
     */
    public function getOfficeSummary(int $accountId, Carbon $startDate, Carbon $endDate): array
    {
        $baseQuery = Grade::whereHas('call', fn($q) => $q->where('account_id', $accountId))
            ->where('status', 'submitted')
            ->whereBetween('grading_completed_at', [$startDate, $endDate]);

        $callsGraded = (clone $baseQuery)->count();

        // Get average weighted score (0-100)
        $avgScore = $this->calculateAveragePercentageScore($accountId, $startDate, $endDate);

        // Appointment rate
        $appointmentStats = $this->getAppointmentStats($accountId, $startDate, $endDate);

        // Calculate trend vs prior period
        $periodDays = $startDate->diffInDays($endDate);
        $priorStart = $startDate->copy()->subDays($periodDays + 1);
        $priorEnd = $startDate->copy()->subDay();
        $priorAvgScore = $this->calculateAveragePercentageScore($accountId, $priorStart, $priorEnd);

        $trend = $priorAvgScore > 0 ? round($avgScore - $priorAvgScore, 1) : 0;
        $trendDirection = $trend > 0 ? 'up' : ($trend < 0 ? 'down' : 'flat');

        return [
            'calls_graded' => $callsGraded,
            'avg_score' => round($avgScore, 1),
            'appt_rate' => $appointmentStats['rate'],
            'appt_total' => $appointmentStats['total'],
            'appt_solid' => $appointmentStats['solid'],
            'trend' => abs($trend),
            'trend_direction' => $trendDirection,
        ];
    }

    /**
     * Get office-wide category averages
     */
    public function getOfficeCategoryAverages(int $accountId, Carbon $startDate, Carbon $endDate): Collection
    {
        return GradeCategoryScore::whereHas('grade', function ($q) use ($accountId, $startDate, $endDate) {
            $q->whereHas('call', fn($cq) => $cq->where('account_id', $accountId))
                ->where('status', 'submitted')
                ->whereBetween('grading_completed_at', [$startDate, $endDate]);
        })
            ->join('rubric_categories', 'grade_category_scores.rubric_category_id', '=', 'rubric_categories.id')
            ->where('rubric_categories.is_active', true)
            ->select(
                'rubric_categories.id',
                'rubric_categories.name',
                'rubric_categories.weight',
                DB::raw('AVG(score) as avg_score'),
                DB::raw('COUNT(*) as sample_count')
            )
            ->groupBy('rubric_categories.id', 'rubric_categories.name', 'rubric_categories.weight')
            ->orderBy('rubric_categories.sort_order')
            ->get()
            ->map(function ($cat) {
                $cat->avg_score = round($cat->avg_score, 2);
                $cat->color = $this->getScoreColor($cat->avg_score);
                return $cat;
            });
    }

    /**
     * Get office-wide score trend over time
     */
    public function getOfficeScoreTrend(int $accountId, Carbon $startDate, Carbon $endDate): Collection
    {
        $periodDays = $startDate->diffInDays($endDate);
        $groupBy = $periodDays > 60 ? 'week' : 'day';

        $query = Grade::whereHas('call', fn($q) => $q->where('account_id', $accountId))
            ->where('status', 'submitted')
            ->whereBetween('grading_completed_at', [$startDate, $endDate])
            ->with('categoryScores.rubricCategory');

        if ($groupBy === 'week') {
            $grades = $query->select(
                DB::raw('YEARWEEK(grading_completed_at, 1) as period'),
                DB::raw('MIN(DATE(grading_completed_at)) as date'),
                'id'
            )->get();
        } else {
            $grades = $query->select(
                DB::raw('DATE(grading_completed_at) as period'),
                DB::raw('DATE(grading_completed_at) as date'),
                'id'
            )->get();
        }

        // Group grades by period and calculate average weighted score
        $grouped = $grades->groupBy('period')->map(function ($periodGrades, $period) {
            $scores = [];
            foreach ($periodGrades as $gradeData) {
                $grade = Grade::with('categoryScores.rubricCategory')->find($gradeData->id);
                if ($grade) {
                    $scores[] = $this->calculateGradePercentage($grade);
                }
            }

            return [
                'date' => $periodGrades->first()->date,
                'avg_score' => count($scores) > 0 ? round(array_sum($scores) / count($scores), 1) : 0,
                'count' => count($scores),
            ];
        });

        return $grouped->values();
    }

    /**
     * Get all reps with their stats for comparison table
     */
    public function getRepComparison(int $accountId, Carbon $startDate, Carbon $endDate): Collection
    {
        $categories = RubricCategory::where('is_active', true)->orderBy('sort_order')->get();

        $reps = Rep::where('account_id', $accountId)
            ->where('is_active', true)
            ->whereHas('calls.grades', function ($q) use ($startDate, $endDate) {
                $q->where('status', 'submitted')
                    ->whereBetween('grading_completed_at', [$startDate, $endDate]);
            })
            ->get();

        return $reps->map(function ($rep) use ($startDate, $endDate, $categories) {
            $grades = Grade::whereHas('call', fn($q) => $q->where('rep_id', $rep->id))
                ->where('status', 'submitted')
                ->whereBetween('grading_completed_at', [$startDate, $endDate])
                ->with('categoryScores.rubricCategory')
                ->get();

            // Calculate average weighted percentage
            $percentages = $grades->map(fn($g) => $this->calculateGradePercentage($g))->filter();
            $avgScore = $percentages->count() > 0 ? round($percentages->avg(), 1) : 0;

            // Appointment stats
            $solidCount = $grades->where('appointment_quality', 'solid')->count();
            $tentativeCount = $grades->where('appointment_quality', 'tentative')->count();
            $totalAppts = $solidCount + $tentativeCount + $grades->where('appointment_quality', 'backed_in')->count();
            $apptRate = $grades->count() > 0 ? round(($totalAppts / $grades->count()) * 100, 1) : 0;

            // Calculate trend
            $periodDays = $startDate->diffInDays($endDate);
            $priorStart = $startDate->copy()->subDays($periodDays + 1);
            $priorEnd = $startDate->copy()->subDay();

            $priorGrades = Grade::whereHas('call', fn($q) => $q->where('rep_id', $rep->id))
                ->where('status', 'submitted')
                ->whereBetween('grading_completed_at', [$priorStart, $priorEnd])
                ->with('categoryScores.rubricCategory')
                ->get();

            $priorPercentages = $priorGrades->map(fn($g) => $this->calculateGradePercentage($g))->filter();
            $priorAvgScore = $priorPercentages->count() > 0 ? round($priorPercentages->avg(), 1) : 0;

            $trend = $priorAvgScore > 0 ? round($avgScore - $priorAvgScore, 1) : 0;

            // Category averages for this rep
            $categoryAvgs = [];
            foreach ($categories as $category) {
                $catScores = GradeCategoryScore::whereIn('grade_id', $grades->pluck('id'))
                    ->where('rubric_category_id', $category->id)
                    ->avg('score');
                $categoryAvgs[$category->id] = $catScores ? round($catScores, 2) : null;
            }

            return [
                'id' => $rep->id,
                'name' => $rep->name,
                'email' => $rep->email,
                'calls_graded' => $grades->count(),
                'avg_score' => $avgScore,
                'appt_rate' => $apptRate,
                'trend' => $trend,
                'trend_direction' => $trend > 0 ? 'up' : ($trend < 0 ? 'down' : 'flat'),
                'category_scores' => $categoryAvgs,
                'solid_count' => $solidCount,
                'tentative_count' => $tentativeCount,
            ];
        })->sortByDesc('avg_score')->values();
    }

    /**
     * Get summary stats for a specific rep with office comparison
     */
    public function getRepSummary(int $repId, int $accountId, Carbon $startDate, Carbon $endDate): array
    {
        $rep = Rep::findOrFail($repId);

        $grades = Grade::whereHas('call', fn($q) => $q->where('rep_id', $repId))
            ->where('status', 'submitted')
            ->whereBetween('grading_completed_at', [$startDate, $endDate])
            ->with('categoryScores.rubricCategory')
            ->get();

        // Rep stats
        $percentages = $grades->map(fn($g) => $this->calculateGradePercentage($g))->filter();
        $repAvgScore = $percentages->count() > 0 ? round($percentages->avg(), 1) : 0;

        $solidCount = $grades->where('appointment_quality', 'solid')->count();
        $tentativeCount = $grades->where('appointment_quality', 'tentative')->count();
        $totalAppts = $solidCount + $tentativeCount + $grades->where('appointment_quality', 'backed_in')->count();
        $repApptRate = $grades->count() > 0 ? round(($totalAppts / $grades->count()) * 100, 1) : 0;

        // Office stats for comparison
        $officeStats = $this->getOfficeSummary($accountId, $startDate, $endDate);

        // Unshared grades count
        $unsharedCount = $grades->whereNull('shared_with_rep_at')->count();

        return [
            'rep' => [
                'id' => $rep->id,
                'name' => $rep->name,
                'email' => $rep->email,
            ],
            'calls_graded' => $grades->count(),
            'avg_score' => $repAvgScore,
            'appt_rate' => $repApptRate,
            'solid_count' => $solidCount,
            'tentative_count' => $tentativeCount,
            'office_avg_score' => $officeStats['avg_score'],
            'office_appt_rate' => $officeStats['appt_rate'],
            'score_diff' => round($repAvgScore - $officeStats['avg_score'], 1),
            'appt_diff' => round($repApptRate - $officeStats['appt_rate'], 1),
            'unshared_count' => $unsharedCount,
        ];
    }

    /**
     * Get category averages for a rep vs office
     */
    public function getRepCategoryAverages(int $repId, int $accountId, Carbon $startDate, Carbon $endDate): Collection
    {
        $officeCategories = $this->getOfficeCategoryAverages($accountId, $startDate, $endDate);

        $grades = Grade::whereHas('call', fn($q) => $q->where('rep_id', $repId))
            ->where('status', 'submitted')
            ->whereBetween('grading_completed_at', [$startDate, $endDate])
            ->pluck('id');

        return $officeCategories->map(function ($cat) use ($grades) {
            $repAvg = GradeCategoryScore::whereIn('grade_id', $grades)
                ->where('rubric_category_id', $cat->id)
                ->avg('score');

            $cat->rep_avg_score = $repAvg ? round($repAvg, 2) : null;
            $cat->rep_color = $cat->rep_avg_score ? $this->getScoreColor($cat->rep_avg_score) : 'gray';
            $cat->diff = $cat->rep_avg_score ? round($cat->rep_avg_score - $cat->avg_score, 2) : null;

            return $cat;
        });
    }

    /**
     * Get rep's score trend with office overlay
     */
    public function getRepScoreTrend(int $repId, int $accountId, Carbon $startDate, Carbon $endDate): array
    {
        $periodDays = $startDate->diffInDays($endDate);
        $groupBy = $periodDays > 60 ? 'week' : 'day';

        // Rep trend
        $repGrades = Grade::whereHas('call', fn($q) => $q->where('rep_id', $repId))
            ->where('status', 'submitted')
            ->whereBetween('grading_completed_at', [$startDate, $endDate])
            ->with('categoryScores.rubricCategory');

        if ($groupBy === 'week') {
            $repData = $repGrades->select(
                DB::raw('YEARWEEK(grading_completed_at, 1) as period'),
                DB::raw('MIN(DATE(grading_completed_at)) as date'),
                'id'
            )->get();
        } else {
            $repData = $repGrades->select(
                DB::raw('DATE(grading_completed_at) as period'),
                DB::raw('DATE(grading_completed_at) as date'),
                'id'
            )->get();
        }

        $repTrend = $repData->groupBy('period')->map(function ($periodGrades, $period) {
            $scores = [];
            foreach ($periodGrades as $gradeData) {
                $grade = Grade::with('categoryScores.rubricCategory')->find($gradeData->id);
                if ($grade) {
                    $scores[] = $this->calculateGradePercentage($grade);
                }
            }

            return [
                'date' => $periodGrades->first()->date,
                'avg_score' => count($scores) > 0 ? round(array_sum($scores) / count($scores), 1) : 0,
                'count' => count($scores),
            ];
        })->values();

        // Office trend for overlay
        $officeTrend = $this->getOfficeScoreTrend($accountId, $startDate, $endDate);

        return [
            'rep' => $repTrend,
            'office' => $officeTrend,
        ];
    }

    /**
     * Get rep's recent graded calls
     */
    public function getRepRecentCalls(int $repId, int $limit = 10): Collection
    {
        return Grade::whereHas('call', fn($q) => $q->where('rep_id', $repId))
            ->where('status', 'submitted')
            ->with(['call.project', 'categoryScores.rubricCategory'])
            ->orderByDesc('grading_completed_at')
            ->limit($limit)
            ->get()
            ->map(function ($grade) {
                return [
                    'id' => $grade->id,
                    'call_id' => $grade->call_id,
                    'called_at' => $grade->call->called_at,
                    'project' => $grade->call->project?->name ?? 'Unknown',
                    'score' => $this->calculateGradePercentage($grade),
                    'appointment_quality' => $grade->appointment_quality,
                    'shared' => $grade->shared_with_rep_at !== null,
                    'graded_at' => $grade->grading_completed_at,
                ];
            });
    }

    /**
     * Get call outcomes (funnel stats) from ALL calls (not just graded)
     * All rates use total_calls as denominator for consistent cross-rep comparison
     */
    public function getCallOutcomes(int $accountId, Carbon $startDate, Carbon $endDate): array
    {
        $query = Call::where('account_id', $accountId)
            ->whereBetween('called_at', [$startDate, $endDate]);

        $totalCalls = (clone $query)->count();
        $appointments = (clone $query)->where('sf_appointment_made', true)->count();
        $shows = (clone $query)->where('sf_toured_property', true)->count();
        $sales = (clone $query)->whereNotNull('sf_land_sale')->count();

        return [
            'total_calls' => $totalCalls,
            'appointments' => $appointments,
            'shows' => $shows,
            'sales' => $sales,
            'appt_rate' => $totalCalls > 0 ? round(($appointments / $totalCalls) * 100, 1) : 0,
            'show_rate' => $totalCalls > 0 ? round(($shows / $totalCalls) * 100, 1) : 0,
            'sale_rate' => $totalCalls > 0 ? round(($sales / $totalCalls) * 100, 1) : 0,
        ];
    }

    /**
     * Get call outcomes (funnel stats) for a specific rep from ALL calls
     * All rates use total_calls as denominator for consistent cross-rep comparison
     */
    public function getRepCallOutcomes(int $repId, Carbon $startDate, Carbon $endDate): array
    {
        $query = Call::where('rep_id', $repId)
            ->whereBetween('called_at', [$startDate, $endDate]);

        $totalCalls = (clone $query)->count();
        $appointments = (clone $query)->where('sf_appointment_made', true)->count();
        $shows = (clone $query)->where('sf_toured_property', true)->count();
        $sales = (clone $query)->whereNotNull('sf_land_sale')->count();

        return [
            'total_calls' => $totalCalls,
            'appointments' => $appointments,
            'shows' => $shows,
            'sales' => $sales,
            'appt_rate' => $totalCalls > 0 ? round(($appointments / $totalCalls) * 100, 1) : 0,
            'show_rate' => $totalCalls > 0 ? round(($shows / $totalCalls) * 100, 1) : 0,
            'sale_rate' => $totalCalls > 0 ? round(($sales / $totalCalls) * 100, 1) : 0,
        ];
    }

    /**
     * Calculate weighted percentage score for a single grade
     */
    public function calculateGradePercentage(Grade $grade): float
    {
        $totalWeightedScore = 0;
        $totalWeight = 0;

        foreach ($grade->categoryScores as $categoryScore) {
            if ($categoryScore->score !== null && $categoryScore->rubricCategory) {
                $weight = $categoryScore->rubricCategory->weight;
                $totalWeightedScore += $categoryScore->score * $weight;
                $totalWeight += $weight;
            }
        }

        if ($totalWeight === 0) {
            return 0;
        }

        $maxPossible = $totalWeight * 4;
        return round(($totalWeightedScore / $maxPossible) * 100, 1);
    }

    /**
     * Calculate average percentage score for an account in a date range
     */
    protected function calculateAveragePercentageScore(int $accountId, Carbon $startDate, Carbon $endDate): float
    {
        $grades = Grade::whereHas('call', fn($q) => $q->where('account_id', $accountId))
            ->where('status', 'submitted')
            ->whereBetween('grading_completed_at', [$startDate, $endDate])
            ->with('categoryScores.rubricCategory')
            ->get();

        if ($grades->isEmpty()) {
            return 0;
        }

        $percentages = $grades->map(fn($g) => $this->calculateGradePercentage($g));

        return $percentages->avg();
    }

    /**
     * Get appointment stats for an account
     */
    protected function getAppointmentStats(int $accountId, Carbon $startDate, Carbon $endDate): array
    {
        $grades = Grade::whereHas('call', fn($q) => $q->where('account_id', $accountId))
            ->where('status', 'submitted')
            ->whereBetween('grading_completed_at', [$startDate, $endDate])
            ->get();

        $total = $grades->count();
        $solid = $grades->where('appointment_quality', 'solid')->count();
        $tentative = $grades->where('appointment_quality', 'tentative')->count();
        $backedIn = $grades->where('appointment_quality', 'backed_in')->count();
        $withAppt = $solid + $tentative + $backedIn;

        return [
            'total' => $withAppt,
            'solid' => $solid,
            'tentative' => $tentative,
            'backed_in' => $backedIn,
            'rate' => $total > 0 ? round(($withAppt / $total) * 100, 1) : 0,
        ];
    }

    /**
     * Get color class based on 1-4 score
     */
    protected function getScoreColor(float $score): string
    {
        return match (true) {
            $score >= 3.0 => 'green',
            $score >= 2.5 => 'yellow',
            default => 'red',
        };
    }
}
