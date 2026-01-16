<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transcription Costs - Call Grader</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    @include('admin.partials.nav')

    <div class="max-w-7xl mx-auto px-4 py-6">
        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Transcription Costs</h1>
            <p class="text-gray-600">Monitor Deepgram usage and expenses</p>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow p-4 mb-6">
            <form method="GET" action="{{ route('admin.costs.index') }}" class="flex gap-4 items-end">
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
            </form>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-sm text-gray-500">Total Cost</p>
                <p class="text-2xl font-bold text-gray-900">${{ number_format($overallStats['total_cost'], 2) }}</p>
                @if($comparison['cost_change'] != 0)
                    <p class="text-xs {{ $comparison['cost_change'] > 0 ? 'text-red-600' : 'text-green-600' }}">
                        {{ $comparison['cost_change'] > 0 ? '↑' : '↓' }} {{ abs($comparison['cost_change']) }}% vs last period
                    </p>
                @endif
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-sm text-gray-500">Transcriptions</p>
                <p class="text-2xl font-bold text-blue-600">{{ number_format($overallStats['total_transcriptions']) }}</p>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-sm text-gray-500">Total Minutes</p>
                <p class="text-2xl font-bold text-gray-900">{{ number_format($overallStats['total_minutes'], 1) }}</p>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-sm text-gray-500">Avg Cost/Call</p>
                <p class="text-2xl font-bold text-gray-900">${{ number_format($overallStats['avg_cost_per_call'], 3) }}</p>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-sm text-gray-500">Cost/Minute</p>
                <p class="text-2xl font-bold text-gray-900">${{ number_format($overallStats['cost_per_minute'], 4) }}</p>
            </div>
        </div>

        <!-- Failed Alert -->
        @if($failedCount > 0)
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                <p class="text-red-800">
                    <strong>{{ $failedCount }}</strong> failed transcriptions in this period. Check logs for details.
                </p>
            </div>
        @endif

        <!-- Daily Chart -->
        <div class="bg-white rounded-lg shadow p-4 mb-6">
            <h3 class="font-medium text-gray-900 mb-4">Daily Costs</h3>
            @php
                $maxCost = max(array_column($dailyData, 'cost')) ?: 0.01;
            @endphp
            <div class="h-48 flex items-end gap-1">
                @foreach($dailyData as $day)
                    <div class="flex-1 flex flex-col items-center group">
                        <div
                            class="w-full bg-green-500 rounded-t hover:bg-green-600 transition-colors cursor-pointer"
                            style="height: {{ ($day['cost'] / $maxCost) * 90 }}%; min-height: {{ $day['cost'] > 0 ? '4px' : '0' }}"
                            title="{{ $day['date'] }}: ${{ number_format($day['cost'], 2) }}"
                        ></div>
                    </div>
                @endforeach
            </div>
            <div class="flex justify-between mt-2 text-xs text-gray-400">
                <span>{{ $dailyData[0]['date'] ?? '' }}</span>
                <span>{{ $dailyData[count($dailyData) - 1]['date'] ?? '' }}</span>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Cost by Office -->
            <div class="bg-white rounded-lg shadow">
                <div class="px-4 py-3 border-b">
                    <h3 class="font-medium text-gray-900">Cost by Office</h3>
                </div>
                <div class="p-4">
                    @forelse($costByOffice as $office)
                        <div class="flex items-center justify-between py-2">
                            <span class="text-sm text-gray-700">{{ $office->name }}</span>
                            <div class="text-right">
                                <span class="text-sm font-medium text-gray-900">${{ number_format($office->cost, 2) }}</span>
                                <span class="text-xs text-gray-500 ml-2">({{ $office->count }} calls)</span>
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-gray-500 py-4">
                            No data available.
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Cost by Project -->
            <div class="bg-white rounded-lg shadow">
                <div class="px-4 py-3 border-b">
                    <h3 class="font-medium text-gray-900">Top Projects by Cost</h3>
                </div>
                <div class="p-4">
                    @forelse($costByProject as $project)
                        <div class="flex items-center justify-between py-2">
                            <span class="text-sm text-gray-700">{{ $project->project_name }}</span>
                            <div class="text-right">
                                <span class="text-sm font-medium text-gray-900">${{ number_format($project->cost, 2) }}</span>
                                <span class="text-xs text-gray-500 ml-2">({{ $project->count }} calls)</span>
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-gray-500 py-4">
                            No data available.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</body>
</html>
