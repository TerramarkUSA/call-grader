<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grading Quality - Call Grader</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    @include('admin.partials.nav')

    <div class="max-w-7xl mx-auto px-4 py-6">
        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Grading Quality</h1>
            <p class="text-gray-600">Monitor manager grading patterns and flag suspicious activity</p>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow p-4 mb-6">
            <form method="GET" action="{{ route('admin.quality.index') }}" class="flex gap-4 items-end">
                <div>
                    <label class="block text-sm text-gray-600 mb-1">From</label>
                    <input type="date" name="date_from" value="{{ $filters['date_from'] }}" class="border rounded px-3 py-2 text-sm" />
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">To</label>
                    <input type="date" name="date_to" value="{{ $filters['date_to'] }}" class="border rounded px-3 py-2 text-sm" />
                </div>
                <button type="submit" class="bg-blue-600 text-white rounded px-4 py-2 text-sm hover:bg-blue-700">
                    Apply
                </button>
                <a href="{{ route('admin.quality.audit') }}" class="text-blue-600 hover:text-blue-800 text-sm ml-auto">
                    View All Flagged Grades &rarr;
                </a>
            </form>
        </div>

        <!-- Overall Stats -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-sm text-gray-500">Total Grades</p>
                <p class="text-2xl font-bold text-gray-900">{{ number_format($overallStats['total_grades']) }}</p>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-sm text-gray-500">Quality Rate</p>
                <p class="text-2xl font-bold {{ $overallStats['quality_rate'] >= 90 ? 'text-green-600' : ($overallStats['quality_rate'] >= 75 ? 'text-yellow-600' : 'text-red-600') }}">
                    {{ $overallStats['quality_rate'] }}%
                </p>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-sm text-gray-500">Avg Playback Ratio</p>
                <p class="text-2xl font-bold text-gray-900">{{ $overallStats['avg_playback_ratio'] }}%</p>
            </div>
            <div class="bg-white rounded-lg shadow p-4 bg-red-50">
                <p class="text-sm text-red-600">Flagged (&lt;{{ $thresholds['flag'] }}%)</p>
                <p class="text-2xl font-bold text-red-600">{{ $overallStats['flagged_count'] }}</p>
            </div>
            <div class="bg-white rounded-lg shadow p-4 bg-yellow-50">
                <p class="text-sm text-yellow-600">Warned ({{ $thresholds['flag'] }}-{{ $thresholds['warn'] }}%)</p>
                <p class="text-2xl font-bold text-yellow-600">{{ $overallStats['warned_count'] }}</p>
            </div>
        </div>

        <!-- Manager Stats Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden mb-6">
            <div class="px-4 py-3 border-b">
                <h3 class="font-medium text-gray-900">Manager Quality Stats</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Manager</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Grades</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Avg Score</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Avg Playback</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Playback %</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Flagged</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Warned</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($managerStats as $manager)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $manager->manager_name }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ $manager->total_grades }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ $manager->avg_score }}%</td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ $manager->avg_playback_formatted }}</td>
                                <td class="px-4 py-3">
                                    <span class="text-sm font-medium {{ $manager->avg_playback_ratio >= $thresholds['warn'] ? 'text-green-600' : ($manager->avg_playback_ratio >= $thresholds['flag'] ? 'text-yellow-600' : 'text-red-600') }}">
                                        {{ $manager->avg_playback_ratio }}%
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    @if($manager->flagged_count > 0)
                                        <span class="text-sm font-medium text-red-600">{{ $manager->flagged_count }}</span>
                                    @else
                                        <span class="text-sm text-gray-400">0</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if($manager->warned_count > 0)
                                        <span class="text-sm font-medium text-yellow-600">{{ $manager->warned_count }}</span>
                                    @else
                                        <span class="text-sm text-gray-400">0</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('admin.quality.manager', $manager->manager_id) }}" class="text-blue-600 hover:text-blue-800 text-sm">
                                        Details
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                                    No grading data in this period.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Flagged Grades -->
        @if($flaggedGrades->count() > 0)
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="px-4 py-3 border-b bg-red-50">
                    <h3 class="font-medium text-red-800">Recent Flagged Grades</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Manager</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rep</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Score</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Playback %</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach($flaggedGrades as $grade)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ $grade->manager_name }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ $grade->rep_name }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ $grade->overall_score }}%</td>
                                    <td class="px-4 py-3">
                                        <span class="text-sm font-medium text-red-600">{{ $grade->playback_ratio }}%</span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ \Carbon\Carbon::parse($grade->grading_completed_at)->format('M j, g:ia') }}</td>
                                    <td class="px-4 py-3">
                                        <a href="{{ route('manager.calls.grade', $grade->call_id) }}" class="text-blue-600 hover:text-blue-800 text-sm">
                                            View
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</body>
</html>
