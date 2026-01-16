<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $manager->name }} - Quality Detail - Call Grader</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    @include('admin.partials.nav')

    <div class="max-w-7xl mx-auto px-4 py-6">
        <!-- Header -->
        <div class="mb-6">
            <a href="{{ route('admin.quality.index') }}" class="text-gray-500 hover:text-gray-700 text-sm">
                &larr; Back to Quality Dashboard
            </a>
            <h1 class="text-2xl font-bold text-gray-900 mt-2">{{ $manager->name }}</h1>
            <p class="text-gray-600">{{ $manager->email }}</p>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-sm text-gray-500">Total Grades</p>
                <p class="text-2xl font-bold text-gray-900">{{ $stats['total_grades'] }}</p>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-sm text-gray-500">Avg Score</p>
                <p class="text-2xl font-bold text-blue-600">{{ $stats['avg_score'] }}%</p>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-sm text-gray-500">Avg Playback %</p>
                @php
                    $playbackColor = $stats['avg_playback_ratio'] >= $thresholds['warn'] ? 'text-green-600' :
                        ($stats['avg_playback_ratio'] >= $thresholds['flag'] ? 'text-yellow-600' : 'text-red-600');
                @endphp
                <p class="text-2xl font-bold {{ $playbackColor }}">{{ $stats['avg_playback_ratio'] }}%</p>
            </div>
            <div class="bg-white rounded-lg shadow p-4 bg-red-50">
                <p class="text-sm text-red-600">Flagged</p>
                <p class="text-2xl font-bold text-red-600">{{ $stats['flagged_count'] }}</p>
            </div>
            <div class="bg-white rounded-lg shadow p-4 bg-yellow-50">
                <p class="text-sm text-yellow-600">Warned</p>
                <p class="text-2xl font-bold text-yellow-600">{{ $stats['warned_count'] }}</p>
            </div>
        </div>

        <!-- Activity Chart -->
        @if(count($dailyActivity) > 0)
            <div class="bg-white rounded-lg shadow p-4 mb-6">
                <h3 class="font-medium text-gray-900 mb-4">Daily Activity</h3>
                @php
                    $maxActivity = max($dailyActivity) ?: 1;
                @endphp
                <div class="h-32 flex items-end gap-1">
                    @foreach($dailyActivity as $date => $count)
                        <div class="flex-1">
                            <div
                                class="w-full bg-blue-500 rounded-t hover:bg-blue-600"
                                style="height: {{ ($count / $maxActivity) * 90 }}%; min-height: {{ $count > 0 ? '4px' : '0' }}"
                                title="{{ $date }}: {{ $count }} grades"
                            ></div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Grades Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-4 py-3 border-b">
                <h3 class="font-medium text-gray-900">All Grades</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rep</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Project</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Score</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Playback %</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($grades as $grade)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm text-gray-900">{{ $grade->rep_name }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ $grade->project_name }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ $grade->overall_score }}%</td>
                                <td class="px-4 py-3">
                                    @php
                                        $ratio = $grade->playback_ratio;
                                        $ratioColor = $ratio === null ? 'text-gray-400' :
                                            ($ratio >= $thresholds['warn'] ? 'text-green-600' :
                                            ($ratio >= $thresholds['flag'] ? 'text-yellow-600' : 'text-red-600'));
                                    @endphp
                                    <span class="text-sm font-medium {{ $ratioColor }}">
                                        {{ $ratio !== null ? $ratio . '%' : '—' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    @if($ratio !== null && $ratio < $thresholds['flag'])
                                        <span class="text-xs px-2 py-0.5 rounded bg-red-100 text-red-800">Flagged</span>
                                    @elseif($ratio !== null && $ratio < $thresholds['warn'])
                                        <span class="text-xs px-2 py-0.5 rounded bg-yellow-100 text-yellow-800">Warned</span>
                                    @else
                                        <span class="text-xs px-2 py-0.5 rounded bg-green-100 text-green-800">OK</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ \Carbon\Carbon::parse($grade->grading_completed_at)->format('M j, g:ia') }}</td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('manager.calls.grade', $grade->call_id) }}" class="text-blue-600 hover:text-blue-800 text-sm">
                                        View
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                                    No grades found for this period.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if($grades->hasPages())
                <div class="px-4 py-3 border-t flex justify-center">
                    {{ $grades->links() }}
                </div>
            @endif
        </div>
    </div>
</body>
</html>
