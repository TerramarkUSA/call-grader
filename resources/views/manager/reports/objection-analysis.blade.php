<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Objection Analysis Report - Call Grader</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#f9fafb] min-h-screen">
    @include('manager.partials.nav')

    <div class="max-w-7xl mx-auto px-8 py-6">
        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-xl font-semibold text-gray-900">Objection Analysis</h1>
            <p class="text-sm text-gray-500">Track objection types and success rates</p>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
            <form method="GET" action="{{ route('manager.reports.objection-analysis') }}">
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

        <!-- Overall Stats -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 text-center">
                <p class="text-3xl font-semibold text-gray-900">{{ $overallStats['total'] }}</p>
                <p class="text-sm text-gray-500">Total Objections</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 text-center">
                <p class="text-3xl font-semibold text-green-600">{{ $overallStats['overcame'] }}</p>
                <p class="text-sm text-gray-500">Overcame</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 text-center">
                <p class="text-3xl font-semibold text-red-600">{{ $overallStats['failed'] }}</p>
                <p class="text-sm text-gray-500">Failed</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 text-center">
                <p class="text-3xl font-semibold {{ $overallStats['success_rate'] >= 50 ? 'text-green-600' : 'text-red-600' }}">
                    {{ $overallStats['success_rate'] }}%
                </p>
                <p class="text-sm text-gray-500">Success Rate</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- By Objection Type -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                <div class="px-4 py-3 border-b border-gray-100">
                    <h3 class="font-medium text-gray-900">By Objection Type</h3>
                </div>
                <div class="p-4 space-y-4">
                    @forelse($objectionStats as $obj)
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-sm text-gray-900">{{ $obj->name }}</span>
                                <span class="text-sm text-gray-500">{{ $obj->total }} total</span>
                            </div>
                            <div class="flex h-4 rounded-lg overflow-hidden bg-gray-100">
                                @if($obj->total > 0)
                                    <div
                                        class="bg-green-500"
                                        style="width: {{ ($obj->overcame / $obj->total) * 100 }}%"
                                    ></div>
                                    <div
                                        class="bg-red-500"
                                        style="width: {{ ($obj->failed / $obj->total) * 100 }}%"
                                    ></div>
                                @endif
                            </div>
                            <div class="flex justify-between text-xs text-gray-500 mt-1">
                                <span>{{ $obj->success_rate }}% success</span>
                                <span>{{ $obj->overcame }} / {{ $obj->failed }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-sm text-gray-500 py-4">
                            No objections logged in this period.
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- By Rep -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                <div class="px-4 py-3 border-b border-gray-100">
                    <h3 class="font-medium text-gray-900">By Rep</h3>
                </div>
                <div class="p-4 space-y-4">
                    @forelse($repObjectionStats as $rep)
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-sm text-gray-900">{{ $rep->rep_name }}</span>
                                <span class="text-sm text-gray-500">{{ $rep->total }} objections</span>
                            </div>
                            <div class="flex h-4 rounded-lg overflow-hidden bg-gray-100">
                                @if($rep->total > 0)
                                    <div
                                        class="bg-green-500"
                                        style="width: {{ ($rep->overcame / $rep->total) * 100 }}%"
                                    ></div>
                                    <div
                                        class="bg-red-500"
                                        style="width: {{ ($rep->failed / $rep->total) * 100 }}%"
                                    ></div>
                                @endif
                            </div>
                            <div class="flex justify-between text-xs text-gray-500 mt-1">
                                <span>{{ $rep->success_rate }}% success</span>
                                <span>{{ $rep->overcame }} / {{ $rep->failed }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-sm text-gray-500 py-4">
                            No objections logged in this period.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</body>
</html>
