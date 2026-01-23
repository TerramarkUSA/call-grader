<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $rep->name }} Performance - Call Grader</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-[#f9fafb] min-h-screen">
    @include('manager.partials.nav')

    <div class="max-w-7xl mx-auto px-8 py-6">
        <!-- Header with Back Link -->
        <div class="mb-6">
            <a href="{{ route('manager.performance.index', ['account_id' => $account->id]) }}" class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700 mb-2">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Back to Performance Dashboard
            </a>
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900">{{ $rep->name }}</h1>
                    <p class="text-sm text-gray-500">{{ $account->name }} &bull; {{ $rep->email ?? 'No email' }}</p>
                </div>
                @if($summary['unshared_count'] > 0 && $rep->email)
                    <form method="POST" action="{{ route('manager.performance.share-all', $rep) }}" class="inline">
                        @csrf
                        <button
                            type="submit"
                            onclick="return confirm('Send {{ $summary['unshared_count'] }} feedback email(s) to {{ $rep->name }}?')"
                            class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors"
                        >
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                            Share All ({{ $summary['unshared_count'] }})
                        </button>
                    </form>
                @endif
            </div>
        </div>

        <!-- Flash Messages -->
        @if(session('success'))
            <div class="mb-6 bg-green-50 border border-green-200 rounded-xl p-4">
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    <span class="text-sm text-green-700">{{ session('success') }}</span>
                </div>
            </div>
        @endif

        @if(session('error'))
            <div class="mb-6 bg-red-50 border border-red-200 rounded-xl p-4">
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-red-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    <span class="text-sm text-red-700">{{ session('error') }}</span>
                </div>
            </div>
        @endif

        <!-- Date Filters -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
            <form method="GET" action="{{ route('manager.performance.show', $rep) }}">
                <div class="flex gap-4 items-end flex-wrap">
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

        <!-- Summary Cards with Office Comparison -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <div class="text-sm text-gray-500 mb-1">Calls Graded</div>
                <div class="text-2xl font-semibold text-gray-900">{{ $summary['calls_graded'] }}</div>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <div class="text-sm text-gray-500 mb-1">Avg Score</div>
                <div class="flex items-baseline gap-2">
                    <span class="text-2xl font-semibold {{ $summary['avg_score'] >= 75 ? 'text-green-600' : ($summary['avg_score'] >= 50 ? 'text-yellow-600' : 'text-red-600') }}">
                        {{ $summary['avg_score'] }}%
                    </span>
                    <span class="text-xs {{ $summary['score_diff'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        {{ $summary['score_diff'] >= 0 ? '+' : '' }}{{ $summary['score_diff'] }} vs office
                    </span>
                </div>
                <div class="text-xs text-gray-400 mt-1">Office avg: {{ $summary['office_avg_score'] }}%</div>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <div class="text-sm text-gray-500 mb-1">Appointment Rate</div>
                <div class="flex items-baseline gap-2">
                    <span class="text-2xl font-semibold text-gray-900">{{ $summary['appt_rate'] }}%</span>
                    <span class="text-xs {{ $summary['appt_diff'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        {{ $summary['appt_diff'] >= 0 ? '+' : '' }}{{ $summary['appt_diff'] }} vs office
                    </span>
                </div>
                <div class="text-xs text-gray-400 mt-1">{{ $summary['solid_count'] }} solid / {{ $summary['solid_count'] + $summary['tentative_count'] }} total</div>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <div class="text-sm text-gray-500 mb-1">Unshared Feedback</div>
                <div class="text-2xl font-semibold {{ $summary['unshared_count'] > 0 ? 'text-orange-600' : 'text-gray-900' }}">
                    {{ $summary['unshared_count'] }}
                </div>
                @if($summary['unshared_count'] > 0 && !$rep->email)
                    <div class="text-xs text-red-500 mt-1">No email configured</div>
                @endif
            </div>
        </div>

        <!-- Charts Row -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- Category Breakdown Chart -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <h3 class="text-sm font-medium text-gray-700 mb-4">Category Breakdown (Rep vs Office)</h3>
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
                <div class="flex items-center justify-center gap-6 mt-3 text-xs">
                    <div class="flex items-center gap-1.5">
                        <span class="w-3 h-0.5 bg-blue-500 rounded"></span>
                        <span class="text-gray-500">{{ $rep->name }}</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="w-3 h-0.5 bg-gray-300 rounded"></span>
                        <span class="text-gray-500">Office Avg</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Category Details Table -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
            <div class="p-4 border-b border-gray-100">
                <h3 class="text-sm font-medium text-gray-700">Category Performance</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Category</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wide">Weight</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wide">Rep Avg</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wide">Office Avg</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wide">Diff</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($categoryAverages as $cat)
                            <tr class="border-b border-gray-100">
                                <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $cat->name }}</td>
                                <td class="px-4 py-3 text-center text-sm text-gray-500">{{ $cat->weight }}%</td>
                                <td class="px-4 py-3 text-center">
                                    @if($cat->rep_avg_score !== null)
                                        <span class="inline-flex items-center gap-1.5">
                                            <span class="w-2.5 h-2.5 rounded-full {{ $cat->rep_color === 'green' ? 'bg-green-500' : ($cat->rep_color === 'yellow' ? 'bg-yellow-500' : 'bg-red-500') }}"></span>
                                            <span class="text-sm font-medium">{{ number_format($cat->rep_avg_score, 2) }}</span>
                                        </span>
                                    @else
                                        <span class="text-sm text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="inline-flex items-center gap-1.5">
                                        <span class="w-2.5 h-2.5 rounded-full {{ $cat->color === 'green' ? 'bg-green-500' : ($cat->color === 'yellow' ? 'bg-yellow-500' : 'bg-red-500') }}"></span>
                                        <span class="text-sm text-gray-600">{{ number_format($cat->avg_score, 2) }}</span>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @if($cat->diff !== null)
                                        <span class="text-sm font-medium {{ $cat->diff >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                            {{ $cat->diff >= 0 ? '+' : '' }}{{ number_format($cat->diff, 2) }}
                                        </span>
                                    @else
                                        <span class="text-sm text-gray-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Calls Table -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="p-4 border-b border-gray-100">
                <h3 class="text-sm font-medium text-gray-700">Recent Graded Calls</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Project</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wide">Score</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wide">Appointment</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wide">Shared</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wide">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentCalls as $call)
                            <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                                <td class="px-4 py-3">
                                    <div class="text-sm text-gray-900">{{ $call['called_at']->format('M j, Y') }}</div>
                                    <div class="text-xs text-gray-400">{{ $call['called_at']->format('g:i A') }}</div>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ $call['project'] }}</td>
                                <td class="px-4 py-3 text-center">
                                    <span class="text-sm font-medium {{ $call['score'] >= 75 ? 'text-green-600' : ($call['score'] >= 50 ? 'text-yellow-600' : 'text-red-600') }}">
                                        {{ round($call['score']) }}%
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @if($call['appointment_quality'])
                                        <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full
                                            {{ $call['appointment_quality'] === 'solid' ? 'bg-green-100 text-green-700' : '' }}
                                            {{ $call['appointment_quality'] === 'tentative' ? 'bg-yellow-100 text-yellow-700' : '' }}
                                            {{ $call['appointment_quality'] === 'backed_in' ? 'bg-orange-100 text-orange-700' : '' }}
                                        ">
                                            {{ ucfirst(str_replace('_', ' ', $call['appointment_quality'])) }}
                                        </span>
                                    @else
                                        <span class="text-xs text-gray-400">No appt</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @if($call['shared'])
                                        <span class="inline-flex items-center text-green-600">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                            </svg>
                                        </span>
                                    @else
                                        <span class="text-xs text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a
                                        href="{{ route('manager.calls.grade', $call['call_id']) }}"
                                        class="text-sm text-blue-600 hover:text-blue-700 font-medium"
                                    >
                                        View
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500">
                                    No graded calls found for this rep.
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

        // Category Breakdown Chart (Rep vs Office)
        const categoryData = @json($categoryAverages);
        if (categoryData.length > 0) {
            new Chart(document.getElementById('categoryChart'), {
                type: 'bar',
                data: {
                    labels: categoryData.map(c => c.name),
                    datasets: [
                        {
                            label: '{{ $rep->name }}',
                            data: categoryData.map(c => c.rep_avg_score),
                            backgroundColor: 'rgba(59, 130, 246, 0.8)',
                            borderRadius: 4,
                        },
                        {
                            label: 'Office Avg',
                            data: categoryData.map(c => c.avg_score),
                            backgroundColor: 'rgba(156, 163, 175, 0.5)',
                            borderRadius: 4,
                        }
                    ]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                boxWidth: 12,
                                padding: 15
                            }
                        }
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

        // Score Trend Chart with Office Overlay
        const trendData = @json($scoreTrend);
        if (trendData.rep.length > 0 || trendData.office.length > 0) {
            // Merge dates from both datasets
            const allDates = [...new Set([
                ...trendData.rep.map(d => d.date),
                ...trendData.office.map(d => d.date)
            ])].sort();

            // Create lookup maps
            const repMap = Object.fromEntries(trendData.rep.map(d => [d.date, d.avg_score]));
            const officeMap = Object.fromEntries(trendData.office.map(d => [d.date, d.avg_score]));

            new Chart(document.getElementById('trendChart'), {
                type: 'line',
                data: {
                    labels: allDates.map(d => {
                        const date = new Date(d);
                        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
                    }),
                    datasets: [
                        {
                            label: '{{ $rep->name }}',
                            data: allDates.map(d => repMap[d] ?? null),
                            borderColor: 'rgb(59, 130, 246)',
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            tension: 0.3,
                            fill: false,
                            spanGaps: true,
                        },
                        {
                            label: 'Office Avg',
                            data: allDates.map(d => officeMap[d] ?? null),
                            borderColor: 'rgb(156, 163, 175)',
                            backgroundColor: 'transparent',
                            borderDash: [5, 5],
                            tension: 0.3,
                            fill: false,
                            spanGaps: true,
                        }
                    ]
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
