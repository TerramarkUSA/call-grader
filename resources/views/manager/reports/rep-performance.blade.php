<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rep Performance Report - Call Grader</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    @include('manager.partials.nav')

    <div class="max-w-7xl mx-auto px-4 py-6">
        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Rep Performance Report</h1>
            <p class="text-gray-600">Compare scores across reps</p>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow p-4 mb-6">
            <form method="GET" action="{{ route('manager.reports.rep-performance') }}">
                <div class="flex gap-4 items-end flex-wrap">
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">From</label>
                        <input
                            type="date"
                            name="date_from"
                            value="{{ $filters['date_from'] }}"
                            class="border rounded px-3 py-2 text-sm"
                        />
                    </div>
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">To</label>
                        <input
                            type="date"
                            name="date_to"
                            value="{{ $filters['date_to'] }}"
                            class="border rounded px-3 py-2 text-sm"
                        />
                    </div>
                    <button
                        type="submit"
                        class="bg-blue-600 text-white rounded px-4 py-2 text-sm hover:bg-blue-700"
                    >
                        Apply
                    </button>
                </div>
            </form>
        </div>

        <!-- Results Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rep</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Calls</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Avg Score</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Range</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Solid</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tentative</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Backed-in</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($repStats as $rep)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $rep->rep_name }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ $rep->call_count }}</td>
                                <td class="px-4 py-3">
                                    <span class="text-sm font-medium {{ $rep->avg_score >= 85 ? 'text-green-600' : ($rep->avg_score >= 70 ? 'text-blue-600' : ($rep->avg_score >= 50 ? 'text-orange-500' : 'text-red-600')) }}">
                                        {{ round($rep->avg_score) }}%
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    {{ round($rep->min_score) }}% &ndash; {{ round($rep->max_score) }}%
                                </td>
                                <td class="px-4 py-3">
                                    <span class="text-sm text-green-600">{{ $rep->solid_count }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="text-sm text-yellow-600">{{ $rep->tentative_count }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="text-sm text-orange-600">{{ $rep->backed_in_count }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                                    No graded calls in this date range.
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
