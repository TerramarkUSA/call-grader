<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grading Activity Report - Call Grader</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    @include('manager.partials.nav')

    <div class="max-w-7xl mx-auto px-4 py-6">
        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">My Grading Activity</h1>
            <p class="text-gray-600">Track your grading volume and patterns</p>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow p-4 mb-6">
            <form method="GET" action="{{ route('manager.reports.grading-activity') }}">
                <div class="flex gap-4 items-end flex-wrap">
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">Period</label>
                        <select name="period" class="border rounded px-3 py-2 text-sm">
                            <option value="7" {{ $filters['period'] == '7' ? 'selected' : '' }}>Last 7 days</option>
                            <option value="30" {{ $filters['period'] == '30' ? 'selected' : '' }}>Last 30 days</option>
                            <option value="90" {{ $filters['period'] == '90' ? 'selected' : '' }}>Last 90 days</option>
                        </select>
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

        <!-- Stats Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-4 text-center">
                <p class="text-3xl font-bold text-gray-900">{{ $stats['total_period'] }}</p>
                <p class="text-sm text-gray-500">Total Graded</p>
            </div>
            <div class="bg-white rounded-lg shadow p-4 text-center">
                <p class="text-3xl font-bold text-blue-600">{{ $stats['daily_average'] }}</p>
                <p class="text-sm text-gray-500">Daily Average</p>
            </div>
            <div class="bg-white rounded-lg shadow p-4 text-center">
                <p class="text-3xl font-bold text-green-600">{{ $stats['best_day'] }}</p>
                <p class="text-sm text-gray-500">Best Day</p>
            </div>
            <div class="bg-white rounded-lg shadow p-4 text-center">
                <p class="text-3xl font-bold text-orange-600">{{ $stats['streak'] }}</p>
                <p class="text-sm text-gray-500">Current Streak</p>
            </div>
        </div>

        <!-- Activity Chart -->
        <div class="bg-white rounded-lg shadow p-4 mb-6">
            <h3 class="font-medium text-gray-900 mb-4">Daily Activity</h3>
            @php
                $maxDaily = max(array_values($activityData)) ?: 1;
                $dates = array_keys($activityData);
            @endphp
            <div class="h-64 flex items-end gap-0.5">
                @foreach($activityData as $date => $count)
                    <div
                        class="flex-1 bg-blue-500 rounded-t transition-all hover:bg-blue-600 cursor-pointer"
                        style="height: {{ ($count / $maxDaily) * 90 }}%; min-height: {{ $count > 0 ? '4px' : '0' }}"
                        title="{{ $date }}: {{ $count }} calls"
                    ></div>
                @endforeach
            </div>
            <div class="flex justify-between mt-2 text-xs text-gray-400">
                <span>{{ $dates[0] ?? '' }}</span>
                <span>{{ end($dates) ?: '' }}</span>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Hourly Distribution -->
            <div class="bg-white rounded-lg shadow p-4">
                <h3 class="font-medium text-gray-900 mb-4">Time of Day</h3>
                @php
                    $maxHourly = max(array_values($hourlyDistribution)) ?: 1;
                @endphp
                <div class="h-32 flex items-end gap-0.5">
                    @for($hour = 0; $hour < 24; $hour++)
                        @php
                            $count = $hourlyDistribution[$hour] ?? 0;
                        @endphp
                        <div
                            class="flex-1 bg-purple-500 rounded-t"
                            style="height: {{ ($count / $maxHourly) * 90 }}%; min-height: {{ $count > 0 ? '2px' : '0' }}"
                            title="{{ $hour < 12 ? ($hour == 0 ? '12' : $hour) . 'am' : ($hour == 12 ? '12' : $hour - 12) . 'pm' }}: {{ $count }}"
                        ></div>
                    @endfor
                </div>
                <div class="flex justify-between mt-2 text-xs text-gray-400">
                    <span>12am</span>
                    <span>6am</span>
                    <span>12pm</span>
                    <span>6pm</span>
                    <span>12am</span>
                </div>
            </div>

            <!-- Day of Week Distribution -->
            <div class="bg-white rounded-lg shadow p-4">
                <h3 class="font-medium text-gray-900 mb-4">Day of Week</h3>
                @php
                    $maxDay = max(array_values($dayOfWeekDistribution)) ?: 1;
                    $dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                @endphp
                <div class="space-y-2">
                    @for($day = 1; $day <= 7; $day++)
                        @php
                            $count = $dayOfWeekDistribution[$day] ?? 0;
                        @endphp
                        <div class="flex items-center gap-2">
                            <span class="w-8 text-sm text-gray-600">{{ $dayNames[$day - 1] }}</span>
                            <div class="flex-1 h-6 bg-gray-100 rounded overflow-hidden">
                                <div
                                    class="h-full bg-green-500 rounded"
                                    style="width: {{ ($count / $maxDay) * 100 }}%"
                                ></div>
                            </div>
                            <span class="w-8 text-sm text-gray-600 text-right">{{ $count }}</span>
                        </div>
                    @endfor
                </div>
            </div>
        </div>
    </div>
</body>
</html>
