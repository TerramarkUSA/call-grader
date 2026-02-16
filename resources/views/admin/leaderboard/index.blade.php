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

        <!-- Overall Stats -->
        <div class="grid grid-cols-5 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-sm text-gray-500">Total Grades</p>
                <p class="text-2xl font-bold text-gray-900">{{ number_format($overallStats['total_grades']) }}</p>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-sm text-gray-500">Total Skipped</p>
                <p class="text-2xl font-bold text-orange-600">{{ number_format($overallStats['total_skipped']) }}</p>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-sm text-gray-500">Completion Rate</p>
                <p class="text-2xl font-bold {{ $overallStats['avg_completion'] >= 70 ? 'text-green-600' : ($overallStats['avg_completion'] >= 50 ? 'text-yellow-600' : 'text-red-600') }}">
                    {{ $overallStats['avg_completion'] }}%
                </p>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-sm text-gray-500">Total Page Time</p>
                <p class="text-2xl font-bold text-blue-600">{{ $overallStats['total_page_hours'] }}h</p>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-sm text-gray-500">Avg Score</p>
                <p class="text-2xl font-bold text-green-600">{{ round($overallStats['avg_score_all']) }}%</p>
            </div>
        </div>

        <!-- Top Rankings -->
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
            @php
                $rankingLabels = [
                    'byVolume' => 'Most Grades',
                    'byScore' => 'Highest Scores',
                    'byQuality' => 'Best Quality',
                    'byNotes' => 'Most Notes',
                    'byThorough' => 'Most Thorough',
                    'byEfficient' => 'Most Efficient',
                ];
            @endphp
            @foreach($rankingLabels as $key => $title)
                <div class="bg-white rounded-lg shadow p-4">
                    <h3 class="text-sm font-medium text-gray-500 mb-2">{{ $title }}</h3>
                    <ol class="space-y-1">
                        @php $index = 1; @endphp
                        @foreach($rankings[$key] as $id => $name)
                            <li class="text-sm">
                                <span class="text-gray-400">{{ $index }}.</span> {{ $name }}
                            </li>
                            @php $index++; @endphp
                        @endforeach
                        @if(empty($rankings[$key]))
                            <li class="text-sm text-gray-400">No data</li>
                        @endif
                    </ol>
                </div>
            @endforeach
        </div>

        <!-- Full Leaderboard Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <!-- Group Headers -->
                        <tr class="bg-gray-50">
                            <th class="px-4 py-2"></th>
                            <th colspan="5" class="px-4 py-2 text-center text-xs font-semibold text-blue-700 uppercase bg-blue-50 border-l border-r border-blue-100">Volume</th>
                            <th colspan="3" class="px-4 py-2 text-center text-xs font-semibold text-amber-700 uppercase bg-amber-50 border-r border-amber-100">Effort</th>
                            <th colspan="4" class="px-4 py-2 text-center text-xs font-semibold text-green-700 uppercase bg-green-50 border-r border-green-100">Quality</th>
                        </tr>
                        <!-- Column Headers -->
                        <tr class="bg-gray-50 border-t">
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Manager</th>
                            {{-- Volume --}}
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase bg-blue-50/50 border-l border-blue-100">Opened</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase bg-blue-50/50">Transcribed</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase bg-blue-50/50">Graded</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase bg-blue-50/50">Skipped</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase bg-blue-50/50 border-r border-blue-100">Completion</th>
                            {{-- Effort --}}
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase bg-amber-50/50">Page Time</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase bg-amber-50/50">Playback %</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase bg-amber-50/50 border-r border-amber-100">Total Playback</th>
                            {{-- Quality --}}
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase bg-green-50/50">Avg Score</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase bg-green-50/50">Notes</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase bg-green-50/50">Quality Rate</th>
                            <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase bg-green-50/50 border-r border-green-100">Flagged</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($leaderboard as $manager)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $manager->name }}</td>

                                {{-- Volume --}}
                                <td class="px-3 py-3 text-sm text-gray-600 bg-blue-50/20">{{ $manager->opened_count }}</td>
                                <td class="px-3 py-3 text-sm text-gray-600 bg-blue-50/20">{{ $manager->transcribed_count }}</td>
                                <td class="px-3 py-3 text-sm font-medium text-gray-900 bg-blue-50/20">{{ $manager->grades_count }}</td>
                                <td class="px-3 py-3 text-sm text-gray-600 bg-blue-50/20">
                                    @if($manager->skipped_count > 0)
                                        <span class="text-orange-600">{{ $manager->skipped_count }}</span>
                                    @else
                                        <span class="text-gray-400">0</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 bg-blue-50/20">
                                    @if(($manager->grades_count + $manager->skipped_count) > 0)
                                        @php
                                            $compColor = $manager->completion_rate >= 70 ? 'text-green-600' :
                                                ($manager->completion_rate >= 50 ? 'text-yellow-600' : 'text-red-600');
                                        @endphp
                                        <span class="text-sm font-medium {{ $compColor }}">{{ $manager->completion_rate }}%</span>
                                    @else
                                        <span class="text-sm text-gray-400">&mdash;</span>
                                    @endif
                                </td>

                                {{-- Effort --}}
                                <td class="px-3 py-3 bg-amber-50/20">
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
                                <td class="px-3 py-3 bg-amber-50/20">
                                    @if($manager->grades_count > 0)
                                        <span class="text-sm text-gray-600">{{ $manager->avg_playback_ratio }}%</span>
                                    @else
                                        <span class="text-sm text-gray-400">&mdash;</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 bg-amber-50/20">
                                    @if($manager->grades_count > 0)
                                        @php
                                            $hours = floor($manager->total_playback_seconds / 3600);
                                            $minutes = floor(($manager->total_playback_seconds % 3600) / 60);
                                        @endphp
                                        <span class="text-sm text-gray-600">{{ $hours }}h {{ $minutes }}m</span>
                                    @else
                                        <span class="text-sm text-gray-400">&mdash;</span>
                                    @endif
                                </td>

                                {{-- Quality --}}
                                <td class="px-3 py-3 bg-green-50/20">
                                    @if($manager->grades_count > 0)
                                        @php
                                            $scoreColor = $manager->avg_score >= 85 ? 'text-green-600' :
                                                ($manager->avg_score >= 70 ? 'text-blue-600' :
                                                ($manager->avg_score >= 50 ? 'text-yellow-600' : 'text-red-600'));
                                        @endphp
                                        <span class="text-sm font-medium {{ $scoreColor }}">{{ $manager->avg_score }}%</span>
                                    @else
                                        <span class="text-sm text-gray-400">&mdash;</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-sm text-gray-600 bg-green-50/20">{{ $manager->notes_count }}</td>
                                <td class="px-3 py-3 bg-green-50/20">
                                    @if($manager->grades_count > 0)
                                        @php
                                            $qualityColor = $manager->quality_rate >= 90 ? 'text-green-600' :
                                                ($manager->quality_rate >= 75 ? 'text-yellow-600' : 'text-red-600');
                                        @endphp
                                        <span class="text-sm font-medium {{ $qualityColor }}">{{ $manager->quality_rate }}%</span>
                                    @else
                                        <span class="text-sm text-gray-400">&mdash;</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 bg-green-50/20">
                                    @if($manager->flagged_count > 0)
                                        <span class="text-sm text-red-600">{{ $manager->flagged_count }}</span>
                                    @else
                                        <span class="text-sm text-gray-400">0</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="13" class="px-4 py-8 text-center text-gray-500">
                                    No managers found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
