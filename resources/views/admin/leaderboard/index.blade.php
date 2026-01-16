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
        <div class="grid grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-sm text-gray-500">Total Grades</p>
                <p class="text-2xl font-bold text-gray-900">{{ number_format($overallStats['total_grades']) }}</p>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-sm text-gray-500">Total Notes</p>
                <p class="text-2xl font-bold text-blue-600">{{ number_format($overallStats['total_notes']) }}</p>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-sm text-gray-500">Avg Per Manager</p>
                <p class="text-2xl font-bold text-gray-900">{{ $overallStats['avg_grades_per_manager'] }}</p>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-sm text-gray-500">Avg Score</p>
                <p class="text-2xl font-bold text-green-600">{{ round($overallStats['avg_score_all']) }}%</p>
            </div>
        </div>

        <!-- Top Rankings -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-4">
                <h3 class="text-sm font-medium text-gray-500 mb-2">Most Grades</h3>
                <ol class="space-y-1">
                    @php $index = 1; @endphp
                    @foreach($rankings['byVolume'] as $id => $name)
                        <li class="text-sm">
                            <span class="text-gray-400">{{ $index }}.</span> {{ $name }}
                        </li>
                        @php $index++; @endphp
                    @endforeach
                    @if(empty($rankings['byVolume']))
                        <li class="text-sm text-gray-400">No data</li>
                    @endif
                </ol>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <h3 class="text-sm font-medium text-gray-500 mb-2">Highest Scores</h3>
                <ol class="space-y-1">
                    @php $index = 1; @endphp
                    @foreach($rankings['byScore'] as $id => $name)
                        <li class="text-sm">
                            <span class="text-gray-400">{{ $index }}.</span> {{ $name }}
                        </li>
                        @php $index++; @endphp
                    @endforeach
                    @if(empty($rankings['byScore']))
                        <li class="text-sm text-gray-400">No data</li>
                    @endif
                </ol>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <h3 class="text-sm font-medium text-gray-500 mb-2">Best Quality</h3>
                <ol class="space-y-1">
                    @php $index = 1; @endphp
                    @foreach($rankings['byQuality'] as $id => $name)
                        <li class="text-sm">
                            <span class="text-gray-400">{{ $index }}.</span> {{ $name }}
                        </li>
                        @php $index++; @endphp
                    @endforeach
                    @if(empty($rankings['byQuality']))
                        <li class="text-sm text-gray-400">No data</li>
                    @endif
                </ol>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <h3 class="text-sm font-medium text-gray-500 mb-2">Most Notes</h3>
                <ol class="space-y-1">
                    @php $index = 1; @endphp
                    @foreach($rankings['byNotes'] as $id => $name)
                        <li class="text-sm">
                            <span class="text-gray-400">{{ $index }}.</span> {{ $name }}
                        </li>
                        @php $index++; @endphp
                    @endforeach
                    @if(empty($rankings['byNotes']))
                        <li class="text-sm text-gray-400">No data</li>
                    @endif
                </ol>
            </div>
        </div>

        <!-- Full Leaderboard Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Manager</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Grades</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Notes</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Avg Score</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Playback %</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Quality Rate</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Flagged</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($leaderboard as $manager)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $manager->name }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ $manager->grades_count }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ $manager->notes_count }}</td>
                                <td class="px-4 py-3">
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
                                <td class="px-4 py-3">
                                    @if($manager->grades_count > 0)
                                        <span class="text-sm text-gray-600">{{ $manager->avg_playback_ratio }}%</span>
                                    @else
                                        <span class="text-sm text-gray-400">&mdash;</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
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
                                <td class="px-4 py-3">
                                    @if($manager->flagged_count > 0)
                                        <span class="text-sm text-red-600">{{ $manager->flagged_count }}</span>
                                    @else
                                        <span class="text-sm text-gray-400">0</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-gray-500">
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
