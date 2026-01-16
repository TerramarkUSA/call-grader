<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Category Breakdown Report - Call Grader</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    @include('manager.partials.nav')

    <div class="max-w-7xl mx-auto px-4 py-6">
        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Category Breakdown</h1>
            <p class="text-gray-600">See which rubric categories are strongest and weakest</p>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow p-4 mb-6">
            <form method="GET" action="{{ route('manager.reports.category-breakdown') }}">
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
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">Rep</label>
                        <select name="rep" class="border rounded px-3 py-2 text-sm">
                            <option value="">All Reps</option>
                            @foreach($reps as $rep)
                                <option value="{{ $rep->id }}" {{ $filters['rep'] == $rep->id ? 'selected' : '' }}>
                                    {{ $rep->name }}
                                </option>
                            @endforeach
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

        <!-- Category Cards -->
        @if($categoryStats->count() > 0)
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach($categoryStats as $cat)
                    @php
                        $avgPercent = $cat->avg_score * 25;
                    @endphp
                    <div class="bg-white rounded-lg shadow p-4">
                        <div class="flex items-center justify-between mb-3">
                            <div>
                                <h3 class="font-medium text-gray-900">{{ $cat->name }}</h3>
                                <p class="text-xs text-gray-500">{{ $cat->weight }}% weight &bull; {{ $cat->sample_count }} scores</p>
                            </div>
                            <div class="text-2xl font-bold {{ $avgPercent >= 85 ? 'text-green-600' : ($avgPercent >= 70 ? 'text-blue-600' : ($avgPercent >= 50 ? 'text-orange-500' : 'text-red-600')) }}">
                                {{ number_format($cat->avg_score, 1) }}/4
                            </div>
                        </div>

                        <!-- Score distribution bar -->
                        <div class="flex h-4 rounded overflow-hidden">
                            @if($cat->sample_count > 0)
                                <div
                                    class="bg-red-500"
                                    style="width: {{ ($cat->score_1_count / $cat->sample_count) * 100 }}%"
                                    title="{{ $cat->score_1_count }} scored 1"
                                ></div>
                                <div
                                    class="bg-orange-500"
                                    style="width: {{ ($cat->score_2_count / $cat->sample_count) * 100 }}%"
                                    title="{{ $cat->score_2_count }} scored 2"
                                ></div>
                                <div
                                    class="bg-blue-500"
                                    style="width: {{ ($cat->score_3_count / $cat->sample_count) * 100 }}%"
                                    title="{{ $cat->score_3_count }} scored 3"
                                ></div>
                                <div
                                    class="bg-green-500"
                                    style="width: {{ ($cat->score_4_count / $cat->sample_count) * 100 }}%"
                                    title="{{ $cat->score_4_count }} scored 4"
                                ></div>
                            @endif
                        </div>

                        <!-- Legend -->
                        <div class="flex justify-between mt-2 text-xs text-gray-500">
                            <span class="flex items-center gap-1">
                                <span class="w-2 h-2 rounded bg-red-500"></span>
                                1: {{ $cat->score_1_count }}
                            </span>
                            <span class="flex items-center gap-1">
                                <span class="w-2 h-2 rounded bg-orange-500"></span>
                                2: {{ $cat->score_2_count }}
                            </span>
                            <span class="flex items-center gap-1">
                                <span class="w-2 h-2 rounded bg-blue-500"></span>
                                3: {{ $cat->score_3_count }}
                            </span>
                            <span class="flex items-center gap-1">
                                <span class="w-2 h-2 rounded bg-green-500"></span>
                                4: {{ $cat->score_4_count }}
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
                No graded calls in this date range.
            </div>
        @endif
    </div>
</body>
</html>
