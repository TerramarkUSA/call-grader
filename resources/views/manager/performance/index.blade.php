<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performance Dashboard - Call Grader</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-[#f9fafb] min-h-screen">
    @include('manager.partials.nav')

    <div class="max-w-7xl mx-auto px-8 py-6">
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-xl font-semibold text-gray-900">Performance Dashboard</h1>
                <p class="text-sm text-gray-500">Office-wide performance metrics and rep comparison</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
            <form method="GET" action="{{ route('manager.performance.index') }}">
                <div class="flex gap-4 items-end flex-wrap">
                    @if($accounts->count() > 1)
                        <div>
                            <label class="block text-sm text-gray-500 mb-1">Account</label>
                            <select
                                name="account_id"
                                onchange="this.form.submit()"
                                class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            >
                                @foreach($accounts as $account)
                                    <option value="{{ $account->id }}" {{ $selectedAccount->id == $account->id ? 'selected' : '' }}>
                                        {{ $account->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div>
                        <label class="block text-sm text-gray-500 mb-1">Date Range</label>
                        <select
                            name="date_filter"
                            onchange="toggleCustomDates(this.value); if(this.value !== 'custom') this.form.submit();"
                            class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        >
                            <option value="7" {{ $dateFilter == '7' ? 'selected' : '' }}>Last 7 days</option>
                            <option value="30" {{ $dateFilter == '30' ? 'selected' : '' }}>Last 30 days</option>
                            <option value="90" {{ $dateFilter == '90' ? 'selected' : '' }}>Last 90 days</option>
                            <option value="this_month" {{ $dateFilter == 'this_month' ? 'selected' : '' }}>This month</option>
                            <option value="last_month" {{ $dateFilter == 'last_month' ? 'selected' : '' }}>Last month</option>
                            <option value="custom" {{ $dateFilter == 'custom' ? 'selected' : '' }}>Custom range</option>
                        </select>
                    </div>
                    <div id="customDates" class="flex gap-2 {{ $dateFilter !== 'custom' ? 'hidden' : '' }}">
                        <div>
                            <label class="block text-sm text-gray-500 mb-1">From</label>
                            <input
                                type="date"
                                name="date_start"
                                value="{{ $startDate->format('Y-m-d') }}"
                                class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            />
                        </div>
                        <div>
                            <label class="block text-sm text-gray-500 mb-1">To</label>
                            <input
                                type="date"
                                name="date_end"
                                value="{{ $endDate->format('Y-m-d') }}"
                                class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            />
                        </div>
                        <div class="flex items-end">
                            <button
                                type="submit"
                                class="bg-blue-600 text-white rounded-lg px-4 py-2 text-sm font-medium hover:bg-blue-700 transition-colors"
                            >
                                Apply
                            </button>
                        </div>
                    </div>
                    <div class="text-sm text-gray-500 self-center">
                        {{ $dateRangeLabel }}
                    </div>
                </div>
            </form>
        </div>

        <!-- Funnel Stats (from ALL calls) -->
        <div class="mb-2">
            <h3 class="text-xs font-medium text-gray-500 uppercase tracking-wide">Call Outcomes</h3>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <div class="text-sm text-gray-500 mb-1">Total Calls</div>
                <div class="text-2xl font-semibold text-gray-900">{{ number_format($callOutcomes['total_calls']) }}</div>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <div class="text-sm text-gray-500 mb-1">Appt Rate</div>
                <div class="text-2xl font-semibold text-gray-900">{{ $callOutcomes['appt_rate'] }}%</div>
                <div class="text-xs text-gray-400 mt-1">{{ number_format($callOutcomes['appointments']) }} appointments</div>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <div class="text-sm text-gray-500 mb-1">Show Rate</div>
                <div class="text-2xl font-semibold text-gray-900">{{ $callOutcomes['show_rate'] }}%</div>
                <div class="text-xs text-gray-400 mt-1">{{ number_format($callOutcomes['shows']) }} shows</div>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <div class="text-sm text-gray-500 mb-1">Sale Rate</div>
                <div class="text-2xl font-semibold text-gray-900">{{ $callOutcomes['sale_rate'] }}%</div>
                <div class="text-xs text-gray-400 mt-1">{{ number_format($callOutcomes['sales']) }} sales</div>
            </div>
        </div>

        <!-- Grading Stats (from graded calls only) -->
        <div class="mb-2">
            <h3 class="text-xs font-medium text-gray-500 uppercase tracking-wide">Grading Performance</h3>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <div class="text-sm text-gray-500 mb-1">Calls Graded</div>
                <div class="text-2xl font-semibold text-gray-900">{{ number_format($summary['calls_graded']) }}</div>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <div class="text-sm text-gray-500 mb-1">Avg Score</div>
                <div class="text-2xl font-semibold {{ $summary['avg_score'] >= 75 ? 'text-green-600' : ($summary['avg_score'] >= 50 ? 'text-yellow-600' : 'text-red-600') }}">
                    {{ $summary['avg_score'] }}%
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <div class="text-sm text-gray-500 mb-1">Appointment Rate</div>
                <div class="text-2xl font-semibold text-gray-900">{{ $summary['appt_rate'] }}%</div>
                <div class="text-xs text-gray-400 mt-1">{{ $summary['appt_solid'] }} solid / {{ $summary['appt_total'] }} total</div>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <div class="text-sm text-gray-500 mb-1">Score Trend</div>
                <div class="flex items-center">
                    @if($summary['trend_direction'] === 'up')
                        <svg class="w-5 h-5 text-green-500 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
                        </svg>
                        <span class="text-2xl font-semibold text-green-600">+{{ $summary['trend'] }}%</span>
                    @elseif($summary['trend_direction'] === 'down')
                        <svg class="w-5 h-5 text-red-500 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                        </svg>
                        <span class="text-2xl font-semibold text-red-600">-{{ $summary['trend'] }}%</span>
                    @else
                        <span class="text-2xl font-semibold text-gray-500">—</span>
                    @endif
                </div>
                <div class="text-xs text-gray-400 mt-1">vs prior period</div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Category Averages Chart -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <h3 class="text-sm font-medium text-gray-700 mb-4">Category Averages</h3>
                <div class="h-64">
                    <canvas id="categoryChart"></canvas>
                </div>
            </div>

            <!-- Score Trend Chart -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <h3 class="text-sm font-medium text-gray-700 mb-4">Score Trend</h3>
                <div class="h-64">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Rep Comparison Table -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="p-4 border-b border-gray-100">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-medium text-gray-700">Rep Performance Comparison</h3>
                    <!-- View Mode Toggle (controlled by Alpine in table wrapper) -->
                    <div class="flex gap-1 bg-gray-100 rounded-lg p-1">
                        <button
                            onclick="setViewMode('summary')"
                            id="btn-summary"
                            class="px-3 py-1 text-xs font-medium rounded-md transition-all bg-white shadow-sm"
                        >
                            Summary
                        </button>
                        <button
                            onclick="setViewMode('details')"
                            id="btn-details"
                            class="px-3 py-1 text-xs font-medium rounded-md transition-all hover:bg-gray-50"
                        >
                            Details
                        </button>
                        <button
                            onclick="setViewMode('heatmap')"
                            id="btn-heatmap"
                            class="px-3 py-1 text-xs font-medium rounded-md transition-all hover:bg-gray-50"
                        >
                            Heatmap
                        </button>
                    </div>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full" id="repTable">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Rep</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wide">Calls</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wide">Appt%</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wide">Show%</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wide">Sale%</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wide">Graded</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wide">Avg Score</th>
                            @foreach($categories as $category)
                                <th
                                    class="px-3 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wide category-col hidden"
                                    title="{{ $category->name }}"
                                >
                                    {{ Str::limit($category->name, 10) }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($repComparison as $rep)
                            <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors cursor-pointer"
                                onclick="window.location='{{ route('manager.performance.show', $rep['id']) }}'">
                                <td class="px-4 py-4">
                                    <div class="text-sm font-medium text-gray-900">{{ $rep['name'] }}</div>
                                    @if($rep['email'])
                                        <div class="text-xs text-gray-400">{{ $rep['email'] }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-4 text-center text-sm text-gray-600">{{ number_format($rep['total_calls']) }}</td>
                                <td class="px-4 py-4 text-center text-sm text-gray-600">{{ $rep['appt_rate'] }}%</td>
                                <td class="px-4 py-4 text-center text-sm text-gray-600">{{ $rep['show_rate'] }}%</td>
                                <td class="px-4 py-4 text-center text-sm text-gray-600">{{ $rep['sale_rate'] }}%</td>
                                <td class="px-4 py-4 text-center text-sm text-gray-500">{{ $rep['calls_graded'] }}</td>
                                <td class="px-4 py-4 text-center">
                                    @if($rep['avg_score'] !== null)
                                        <span class="text-sm font-medium {{ $rep['avg_score'] >= 75 ? 'text-green-600' : ($rep['avg_score'] >= 50 ? 'text-yellow-600' : 'text-red-600') }}">
                                            {{ $rep['avg_score'] }}%
                                        </span>
                                    @else
                                        <span class="text-xs text-gray-400">—</span>
                                    @endif
                                </td>
                                @foreach($categories as $category)
                                    <td class="px-3 py-4 text-center category-col hidden">
                                        @if(isset($rep['category_scores'][$category->id]) && $rep['category_scores'][$category->id] !== null)
                                            <span class="details-view text-sm {{ $rep['category_scores'][$category->id] >= 3.0 ? 'text-green-600' : ($rep['category_scores'][$category->id] >= 2.5 ? 'text-yellow-600' : 'text-red-600') }}">
                                                {{ number_format($rep['category_scores'][$category->id], 1) }}
                                            </span>
                                            <span
                                                class="heatmap-view hidden inline-block w-4 h-4 rounded-full {{ $rep['category_scores'][$category->id] >= 3.0 ? 'bg-green-500' : ($rep['category_scores'][$category->id] >= 2.5 ? 'bg-yellow-500' : 'bg-red-500') }}"
                                                title="{{ $category->name }}: {{ number_format($rep['category_scores'][$category->id], 2) }}"
                                            ></span>
                                        @else
                                            <span class="text-xs text-gray-300">—</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ 7 + $categories->count() }}" class="px-4 py-8 text-center text-sm text-gray-500">
                                    No calls in this date range.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function toggleCustomDates(value) {
            document.getElementById('customDates').classList.toggle('hidden', value !== 'custom');
        }

        // View mode toggle for rep table
        let currentMode = 'summary';
        function setViewMode(mode) {
            currentMode = mode;

            // Update button styles
            ['summary', 'details', 'heatmap'].forEach(m => {
                const btn = document.getElementById('btn-' + m);
                if (m === mode) {
                    btn.classList.add('bg-white', 'shadow-sm');
                    btn.classList.remove('hover:bg-gray-50');
                } else {
                    btn.classList.remove('bg-white', 'shadow-sm');
                    btn.classList.add('hover:bg-gray-50');
                }
            });

            // Show/hide category columns
            const categoryCols = document.querySelectorAll('.category-col');
            categoryCols.forEach(col => {
                col.classList.toggle('hidden', mode === 'summary');
            });

            // Toggle between details and heatmap views
            const detailsViews = document.querySelectorAll('.details-view');
            const heatmapViews = document.querySelectorAll('.heatmap-view');

            detailsViews.forEach(el => {
                el.classList.toggle('hidden', mode === 'heatmap');
            });
            heatmapViews.forEach(el => {
                el.classList.toggle('hidden', mode !== 'heatmap');
            });
        }

        // Category Averages Chart
        const categoryData = @json($categoryAverages);
        if (categoryData.length > 0) {
            new Chart(document.getElementById('categoryChart'), {
                type: 'bar',
                data: {
                    labels: categoryData.map(c => c.name),
                    datasets: [{
                        label: 'Average Score',
                        data: categoryData.map(c => c.avg_score),
                        backgroundColor: categoryData.map(c => {
                            if (c.avg_score >= 3.0) return 'rgba(34, 197, 94, 0.8)';
                            if (c.avg_score >= 2.5) return 'rgba(234, 179, 8, 0.8)';
                            return 'rgba(239, 68, 68, 0.8)';
                        }),
                        borderRadius: 4,
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        x: {
                            min: 0,
                            max: 4,
                            ticks: {
                                stepSize: 1
                            }
                        }
                    }
                }
            });
        }

        // Score Trend Chart
        const trendData = @json($scoreTrend);
        if (trendData.length > 0) {
            new Chart(document.getElementById('trendChart'), {
                type: 'line',
                data: {
                    labels: trendData.map(d => {
                        const date = new Date(d.date);
                        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                    }),
                    datasets: [{
                        label: 'Avg Score',
                        data: trendData.map(d => d.avg_score),
                        borderColor: 'rgb(59, 130, 246)',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.3,
                        fill: true,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            min: 0,
                            max: 100,
                            ticks: {
                                callback: value => value + '%'
                            }
                        }
                    }
                }
            });
        }
    </script>
</body>
</html>
