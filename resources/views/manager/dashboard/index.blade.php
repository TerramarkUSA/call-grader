<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Call Grader</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    @include('manager.partials.nav')

    <div class="max-w-7xl mx-auto px-4 py-6">
        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>
            <p class="text-gray-600">Your grading performance at a glance</p>
        </div>

        <!-- Top Stats Row -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-sm text-gray-500">Calls in Queue</p>
                <p class="text-2xl font-bold text-gray-900">{{ $callsInQueue }}</p>
                <a href="{{ route('manager.calls.index') }}" class="text-xs text-blue-600 hover:text-blue-800 mt-1 inline-block">
                    View Queue
                </a>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-sm text-gray-500">Graded This Week</p>
                <p class="text-2xl font-bold text-gray-900">{{ $gradingStats['graded_this_week'] }}</p>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-sm text-gray-500">Avg Score (All Time)</p>
                <p class="text-2xl font-bold {{ $avgScore >= 85 ? 'text-green-600' : ($avgScore >= 70 ? 'text-blue-600' : ($avgScore >= 50 ? 'text-orange-500' : 'text-red-600')) }}">
                    {{ $avgScore }}%
                </p>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-sm text-gray-500">Avg Score (This Week)</p>
                <p class="text-2xl font-bold {{ $avgScoreThisWeek >= 85 ? 'text-green-600' : ($avgScoreThisWeek >= 70 ? 'text-blue-600' : ($avgScoreThisWeek >= 50 ? 'text-orange-500' : 'text-red-600')) }}">
                    {{ $avgScoreThisWeek }}%
                </p>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left Column: Activity Chart + Recent Grades -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Activity Chart -->
                <div class="bg-white rounded-lg shadow p-4">
                    <h3 class="font-medium text-gray-900 mb-4">Grading Activity (Last 7 Days)</h3>
                    <div class="h-48 flex items-end justify-between gap-2">
                        @php
                            $maxActivity = max(array_values($activityByDay)) ?: 1;
                        @endphp
                        @foreach($activityByDay as $date => $count)
                            <div class="flex-1 flex flex-col items-center">
                                <div
                                    class="w-full bg-blue-500 rounded-t transition-all"
                                    style="height: {{ ($count / $maxActivity) * 80 }}%; min-height: {{ $count > 0 ? '4px' : '0' }}"
                                ></div>
                                <span class="text-xs text-gray-400 mt-1">{{ \Carbon\Carbon::parse($date)->format('D') }}</span>
                                <span class="text-xs font-medium text-gray-600">{{ $count }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Recent Grades -->
                <div class="bg-white rounded-lg shadow">
                    <div class="px-4 py-3 border-b flex items-center justify-between">
                        <h3 class="font-medium text-gray-900">Recent Grades</h3>
                        <a href="{{ route('manager.graded-calls') }}" class="text-sm text-blue-600 hover:text-blue-800">
                            View All &rarr;
                        </a>
                    </div>
                    <div class="divide-y">
                        @forelse($recentGrades as $grade)
                            <div class="px-4 py-3 flex items-center justify-between hover:bg-gray-50">
                                <div>
                                    <p class="font-medium text-gray-900">{{ $grade->call?->rep?->name ?? 'Unknown Rep' }}</p>
                                    <p class="text-sm text-gray-500">{{ $grade->call?->project?->name ?? 'Unknown Project' }}</p>
                                </div>
                                @php
                                    $scorePercent = $grade->overall_score ? ($grade->overall_score / 4) * 100 : 0;
                                @endphp
                                <div class="text-right">
                                    <p class="font-medium {{ $scorePercent >= 85 ? 'text-green-600' : ($scorePercent >= 70 ? 'text-blue-600' : ($scorePercent >= 50 ? 'text-orange-500' : 'text-red-600')) }}">
                                        {{ round($scorePercent) }}%
                                    </p>
                                    <p class="text-xs text-gray-400">{{ $grade->grading_completed_at?->format('M j') ?? '-' }}</p>
                                </div>
                            </div>
                        @empty
                            <div class="px-4 py-8 text-center text-gray-500">
                                No grades yet. Start grading calls!
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- Right Column: Insights -->
            <div class="space-y-6">
                <!-- Drafts Pending -->
                @if($gradingStats['drafts_pending'] > 0)
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                        <h3 class="font-medium text-yellow-800 mb-1">Drafts Pending</h3>
                        <p class="text-2xl font-bold text-yellow-900">{{ $gradingStats['drafts_pending'] }}</p>
                        <p class="text-sm text-yellow-700 mt-1">Unfinished grades waiting to be submitted</p>
                    </div>
                @endif

                <!-- Top Performers -->
                <div class="bg-white rounded-lg shadow p-4">
                    <h3 class="font-medium text-gray-900 mb-3">Top Performers</h3>
                    @if($topReps->count() > 0)
                        <div class="space-y-2">
                            @foreach($topReps as $index => $rep)
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <span class="text-lg">{{ ['1st', '2nd', '3rd', '4th', '5th'][$index] ?? ($index + 1) . '.' }}</span>
                                        <span class="text-sm text-gray-700">{{ $rep->rep_name }}</span>
                                    </div>
                                    <span class="text-sm font-medium text-green-600">{{ round($rep->avg_score) }}%</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-gray-500">Need 3+ graded calls per rep</p>
                    @endif
                </div>

                <!-- Needs Coaching -->
                <div class="bg-white rounded-lg shadow p-4">
                    <h3 class="font-medium text-gray-900 mb-3">Needs Coaching</h3>
                    @if($bottomReps->count() > 0)
                        <div class="space-y-2">
                            @foreach($bottomReps as $rep)
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-700">{{ $rep->rep_name }}</span>
                                    <span class="text-sm font-medium text-red-600">{{ round($rep->avg_score) }}%</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-gray-500">Need 3+ graded calls per rep</p>
                    @endif
                </div>

                <!-- Weakest Categories -->
                <div class="bg-white rounded-lg shadow p-4">
                    <h3 class="font-medium text-gray-900 mb-3">Focus Areas</h3>
                    <p class="text-xs text-gray-500 mb-2">Categories with lowest average scores</p>
                    @if($weakestCategories->count() > 0)
                        <div class="space-y-2">
                            @foreach($weakestCategories as $cat)
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-700">{{ $cat->name }}</span>
                                    <span class="text-sm font-medium text-orange-600">{{ number_format($cat->avg_score, 1) }}/4</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-gray-500">Grade more calls to see insights</p>
                    @endif
                    <a href="{{ route('manager.reports.category-breakdown') }}" class="block mt-3 text-sm text-blue-600 hover:text-blue-800">
                        View Category Report &rarr;
                    </a>
                </div>

                <!-- Notes Stats -->
                <div class="bg-white rounded-lg shadow p-4">
                    <h3 class="font-medium text-gray-900 mb-3">Coaching Notes</h3>
                    <div class="grid grid-cols-2 gap-4 text-center">
                        <div>
                            <p class="text-2xl font-bold text-gray-900">{{ $notesStats['total_notes'] }}</p>
                            <p class="text-xs text-gray-500">Total Notes</p>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-gray-900">{{ $notesStats['notes_this_week'] }}</p>
                            <p class="text-xs text-gray-500">This Week</p>
                        </div>
                    </div>
                    @if($notesStats['objections_logged'] > 0)
                        <div class="mt-3 pt-3 border-t">
                            @php
                                $successRate = round(($notesStats['objections_overcame'] / $notesStats['objections_logged']) * 100);
                            @endphp
                            <p class="text-sm text-gray-600">
                                Objection Success Rate:
                                <span class="font-medium {{ $successRate >= 50 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $successRate }}%
                                </span>
                            </p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</body>
</html>
