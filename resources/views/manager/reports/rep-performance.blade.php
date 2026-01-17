<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rep Performance Report - Call Grader</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#f9fafb] min-h-screen">
    @include('manager.partials.nav')

    <div class="max-w-7xl mx-auto px-8 py-6">
        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-xl font-semibold text-gray-900">Rep Performance Report</h1>
            <p class="text-sm text-gray-500">Compare scores across reps</p>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
            <form method="GET" action="{{ route('manager.reports.rep-performance') }}">
                <div class="flex gap-4 items-end flex-wrap">
                    <div>
                        <label class="block text-sm text-gray-500 mb-1">From</label>
                        <input
                            type="date"
                            name="date_from"
                            value="{{ $filters['date_from'] }}"
                            class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        />
                    </div>
                    <div>
                        <label class="block text-sm text-gray-500 mb-1">To</label>
                        <input
                            type="date"
                            name="date_to"
                            value="{{ $filters['date_to'] }}"
                            class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        />
                    </div>
                    <button
                        type="submit"
                        class="bg-blue-600 text-white rounded-lg px-4 py-2 text-sm font-medium hover:bg-blue-700 transition-colors"
                    >
                        Apply Filters
                    </button>
                </div>
            </form>
        </div>

        <!-- Results Table -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Rep</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Calls</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Avg Score</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Range</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Solid</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Tentative</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Backed-in</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($repStats as $rep)
                            <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                                <td class="px-4 py-4 text-sm font-medium text-gray-900">{{ $rep->rep_name }}</td>
                                <td class="px-4 py-4 text-sm text-gray-500">{{ $rep->call_count }}</td>
                                <td class="px-4 py-4">
                                    <span class="text-sm font-medium {{ $rep->avg_score >= 85 ? 'text-green-600' : ($rep->avg_score >= 70 ? 'text-blue-600' : ($rep->avg_score >= 50 ? 'text-yellow-600' : 'text-red-600')) }}">
                                        {{ round($rep->avg_score) }}%
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-sm text-gray-500">
                                    {{ round($rep->min_score) }}% &ndash; {{ round($rep->max_score) }}%
                                </td>
                                <td class="px-4 py-4">
                                    <span class="rounded-full px-2.5 py-0.5 text-xs font-medium bg-green-100 text-green-700">{{ $rep->solid_count }}</span>
                                </td>
                                <td class="px-4 py-4">
                                    <span class="rounded-full px-2.5 py-0.5 text-xs font-medium bg-yellow-100 text-yellow-700">{{ $rep->tentative_count }}</span>
                                </td>
                                <td class="px-4 py-4">
                                    <span class="rounded-full px-2.5 py-0.5 text-xs font-medium bg-orange-100 text-orange-700">{{ $rep->backed_in_count }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500">
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
