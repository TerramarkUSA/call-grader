<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grade Audit - Call Grader</title>
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
            <h1 class="text-2xl font-bold text-gray-900 mt-2">Grade Audit</h1>
            <p class="text-gray-600">Review grades by quality status</p>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow p-4 mb-6">
            <form method="GET" action="{{ route('admin.quality.audit') }}" class="flex gap-4 items-end flex-wrap">
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Status</label>
                    <select name="filter" class="border rounded px-3 py-2 text-sm">
                        <option value="flagged" {{ $filters['filter'] === 'flagged' ? 'selected' : '' }}>Flagged (&lt;{{ $thresholds['flag'] }}%)</option>
                        <option value="warned" {{ $filters['filter'] === 'warned' ? 'selected' : '' }}>Warned ({{ $thresholds['flag'] }}-{{ $thresholds['warn'] }}%)</option>
                        <option value="all" {{ $filters['filter'] === 'all' ? 'selected' : '' }}>All Grades</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Manager</label>
                    <select name="manager" class="border rounded px-3 py-2 text-sm">
                        <option value="">All Managers</option>
                        @foreach($managers as $m)
                            <option value="{{ $m->id }}" {{ $filters['manager'] == $m->id ? 'selected' : '' }}>{{ $m->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="bg-blue-600 text-white rounded px-4 py-2 text-sm hover:bg-blue-700">
                    Apply
                </button>
            </form>
        </div>

        <!-- Grades Table -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Manager</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rep</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Project</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Score</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Playback %</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Call Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Graded</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse($grades as $grade)
                            @php
                                $ratio = $grade->playback_ratio;
                                $rowClass = $ratio !== null && $ratio < $thresholds['flag'] ? 'bg-red-50' :
                                    ($ratio !== null && $ratio < $thresholds['warn'] ? 'bg-yellow-50' : '');
                                $ratioColor = $ratio === null ? 'text-gray-400' :
                                    ($ratio >= $thresholds['warn'] ? 'text-green-600' :
                                    ($ratio >= $thresholds['flag'] ? 'text-yellow-600' : 'text-red-600'));
                            @endphp
                            <tr class="hover:bg-gray-50 {{ $rowClass }}">
                                <td class="px-4 py-3 text-sm text-gray-900">{{ $grade->manager_name }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ $grade->rep_name }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ $grade->project_name }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ $grade->overall_score }}%</td>
                                <td class="px-4 py-3">
                                    <span class="text-sm font-medium {{ $ratioColor }}">
                                        {{ $ratio !== null ? $ratio . '%' : '—' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ $grade->called_at ? \Carbon\Carbon::parse($grade->called_at)->format('M j') : '—' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ \Carbon\Carbon::parse($grade->grading_completed_at)->format('M j') }}</td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('manager.calls.grade', $grade->call_id) }}" class="text-blue-600 hover:text-blue-800 text-sm">
                                        View
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                                    No grades match this filter.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if($grades->hasPages())
                <div class="px-4 py-3 border-t flex justify-between items-center">
                    <p class="text-sm text-gray-600">
                        Showing {{ $grades->firstItem() }} to {{ $grades->lastItem() }} of {{ $grades->total() }}
                    </p>
                    <div>
                        {{ $grades->links() }}
                    </div>
                </div>
            @endif
        </div>
    </div>
</body>
</html>
