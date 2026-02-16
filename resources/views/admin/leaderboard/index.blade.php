<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Leaderboard - Call Grader</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    @include('admin.partials.nav')

    <div class="max-w-7xl mx-auto px-4 py-6">
        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Manager Leaderboard</h1>
            <p class="text-gray-600">Compare manager activity and performance</p>
        </div>

        <!-- Period Filter -->
        <div class="bg-white rounded-lg shadow p-4 mb-6">
            <div class="flex gap-2">
                @php
                    $periods = [
                        'today' => 'Today',
                        'yesterday' => 'Yesterday',
                        'week' => 'This Week',
                        'month' => 'This Month',
                        'quarter' => 'This Quarter',
                        'all' => 'All Time',
                    ];
                @endphp
                @foreach($periods as $value => $label)
                    <a
                        href="{{ route('admin.leaderboard.index', ['period' => $value]) }}"
                        class="px-4 py-2 text-sm rounded transition-colors {{ $filters['period'] === $value ? 'bg-blue-600 text-white' : 'bg-gray-100 hover:bg-gray-200 text-gray-700' }}"
                    >
                        {{ $label }}
                    </a>
                @endforeach
            </div>
        </div>

        <!-- Static Stats Cards -->
        <div class="grid grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-sm text-gray-500">Total Processed</p>
                <p class="text-2xl font-bold text-gray-900">{{ number_format($overallStats['total_processed']) }}</p>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-sm text-gray-500">Total Graded</p>
                <p class="text-2xl font-bold text-blue-600">{{ number_format($overallStats['total_grades']) }}</p>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-sm text-gray-500">Avg Completion</p>
                @php
                    $compColor = $overallStats['avg_completion'] >= 70 ? 'text-green-600' :
                        ($overallStats['avg_completion'] >= 50 ? 'text-yellow-600' : 'text-red-600');
                @endphp
                <p class="text-2xl font-bold {{ $compColor }}">{{ $overallStats['avg_completion'] }}%</p>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-sm text-gray-500">Avg Score</p>
                @php
                    $avgScoreColor = $overallStats['avg_score_all'] >= 85 ? 'text-green-600' :
                        ($overallStats['avg_score_all'] >= 70 ? 'text-blue-600' :
                        ($overallStats['avg_score_all'] >= 50 ? 'text-yellow-600' : 'text-red-600'));
                @endphp
                <p class="text-2xl font-bold {{ $avgScoreColor }}">{{ $overallStats['avg_score_all'] }}%</p>
            </div>
        </div>

        <!-- Tabs -->
        <div class="mb-6">
            <div class="border-b border-gray-200">
                <nav class="flex gap-6" id="leaderboard-tabs">
                    <button
                        type="button"
                        data-tab="activity"
                        class="tab-btn pb-3 text-sm font-semibold border-b-2 border-blue-600 text-blue-600"
                    >
                        Activity Overview
                    </button>
                    <button
                        type="button"
                        data-tab="quality"
                        class="tab-btn pb-3 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700"
                    >
                        Grading Quality
                    </button>
                </nav>
            </div>
        </div>

        <!-- Tab 1: Activity Overview -->
        <div id="tab-activity" class="tab-panel">
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Manager</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Opened</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Processed</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Graded</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Skipped</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Abandoned</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Completion %</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Page Time</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse($leaderboard as $manager)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $manager->name }}</td>
                                    <td class="px-3 py-3 text-sm text-gray-600">{{ $manager->opened_count }}</td>
                                    <td class="px-3 py-3 text-sm text-gray-600">{{ $manager->transcribed_count }}</td>
                                    <td class="px-3 py-3 text-sm font-medium text-gray-900">{{ $manager->grades_count }}</td>
                                    <td class="px-3 py-3 text-sm">
                                        @if($manager->skipped_count > 0)
                                            <span class="text-orange-600">{{ $manager->skipped_count }}</span>
                                        @else
                                            <span class="text-gray-400">0</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3 text-sm">
                                        @if($manager->abandoned_count > 0)
                                            <span class="text-red-500">{{ $manager->abandoned_count }}</span>
                                        @else
                                            <span class="text-gray-400">0</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3">
                                        @if(($manager->grades_count + $manager->skipped_count + $manager->abandoned_count) > 0)
                                            @php
                                                $compColor = $manager->completion_rate >= 70 ? 'text-green-600' :
                                                    ($manager->completion_rate >= 50 ? 'text-yellow-600' : 'text-red-600');
                                            @endphp
                                            <span class="text-sm font-medium {{ $compColor }}">{{ $manager->completion_rate }}%</span>
                                        @else
                                            <span class="text-sm text-gray-400">&mdash;</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3">
                                        @if($manager->total_page_seconds > 0)
                                            @php
                                                $pageHours = floor($manager->total_page_seconds / 3600);
                                                $pageMinutes = floor(($manager->total_page_seconds % 3600) / 60);
                                            @endphp
                                            <span class="text-sm text-gray-600">{{ $pageHours }}h {{ $pageMinutes }}m</span>
                                        @else
                                            <span class="text-sm text-gray-400">&mdash;</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                                        No managers found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Tab 2: Grading Quality -->
        <div id="tab-quality" class="tab-panel" style="display: none;">
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Manager</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Grades</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Avg Score</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Playback %</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total Playback</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Notes</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Quality Rate</th>
                                <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Flagged</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @php $hasQualityRows = false; @endphp
                            @foreach($leaderboard as $manager)
                                @if($manager->grades_count > 0)
                                    @php $hasQualityRows = true; @endphp
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $manager->name }}</td>
                                        <td class="px-3 py-3 text-sm font-medium text-gray-900">{{ $manager->grades_count }}</td>
                                        <td class="px-3 py-3">
                                            @php
                                                $scoreColor = $manager->avg_score >= 85 ? 'text-green-600' :
                                                    ($manager->avg_score >= 70 ? 'text-blue-600' :
                                                    ($manager->avg_score >= 50 ? 'text-yellow-600' : 'text-red-600'));
                                            @endphp
                                            <span class="text-sm font-medium {{ $scoreColor }}">{{ $manager->avg_score }}%</span>
                                        </td>
                                        <td class="px-3 py-3">
                                            @php
                                                $playbackColor = $manager->avg_playback_ratio >= 85 ? 'text-green-600' :
                                                    ($manager->avg_playback_ratio >= 70 ? 'text-blue-600' :
                                                    ($manager->avg_playback_ratio >= 50 ? 'text-yellow-600' : 'text-red-600'));
                                            @endphp
                                            <span class="text-sm font-medium {{ $playbackColor }}">{{ $manager->avg_playback_ratio }}%</span>
                                        </td>
                                        <td class="px-3 py-3">
                                            @php
                                                $hours = floor($manager->total_playback_seconds / 3600);
                                                $minutes = floor(($manager->total_playback_seconds % 3600) / 60);
                                            @endphp
                                            <span class="text-sm text-gray-600">{{ $hours }}h {{ $minutes }}m</span>
                                        </td>
                                        <td class="px-3 py-3 text-sm text-gray-600">{{ $manager->notes_count }}</td>
                                        <td class="px-3 py-3">
                                            @php
                                                $qualityColor = $manager->quality_rate >= 90 ? 'text-green-600' :
                                                    ($manager->quality_rate >= 75 ? 'text-yellow-600' : 'text-red-600');
                                            @endphp
                                            <span class="text-sm font-medium {{ $qualityColor }}">{{ $manager->quality_rate }}%</span>
                                        </td>
                                        <td class="px-3 py-3">
                                            @if($manager->flagged_count > 0)
                                                <span class="text-sm text-red-600">{{ $manager->flagged_count }}</span>
                                            @else
                                                <span class="text-sm text-gray-400">0</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                            @if(!$hasQualityRows)
                                <tr>
                                    <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                                        No grades submitted yet.
                                    </td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Rankings -->
        <div class="grid grid-cols-3 gap-4 mt-6">
            @php
                $rankingLabels = [
                    'byThorough' => ['Most Thorough', 'Highest completion %, min 10 processed'],
                    'byEfficient' => ['Most Efficient', 'Most grades per page-hour, min 5 grades'],
                    'byScore' => ['Top Scorer', 'Highest avg score, min 5 grades'],
                ];
            @endphp
            @foreach($rankingLabels as $key => [$title, $subtitle])
                <div class="bg-white rounded-lg shadow p-4">
                    <h3 class="text-sm font-semibold text-gray-900 mb-1">{{ $title }}</h3>
                    <p class="text-xs text-gray-400 mb-3">{{ $subtitle }}</p>
                    <ol class="space-y-1">
                        @php $index = 1; @endphp
                        @foreach($rankings[$key] as $id => $name)
                            <li class="text-sm">
                                <span class="text-gray-400 font-medium">{{ $index }}.</span> {{ $name }}
                            </li>
                            @php $index++; @endphp
                        @endforeach
                        @if(empty($rankings[$key]))
                            <li class="text-sm text-gray-400">Not enough data</li>
                        @endif
                    </ol>
                </div>
            @endforeach
        </div>
    </div>

    <script>
        const tabs = document.querySelectorAll('.tab-btn');
        const panels = document.querySelectorAll('.tab-panel');

        tabs.forEach(btn => {
            btn.addEventListener('click', () => {
                const target = btn.dataset.tab;

                tabs.forEach(t => {
                    t.classList.remove('border-blue-600', 'text-blue-600', 'font-semibold');
                    t.classList.add('border-transparent', 'text-gray-500', 'font-medium');
                });
                btn.classList.remove('border-transparent', 'text-gray-500', 'font-medium');
                btn.classList.add('border-blue-600', 'text-blue-600', 'font-semibold');

                panels.forEach(p => p.style.display = 'none');
                document.getElementById('tab-' + target).style.display = 'block';
            });
        });
    </script>
</body>
</html>
