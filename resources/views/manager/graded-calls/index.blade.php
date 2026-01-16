<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Graded Calls - Call Grader</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    @include('manager.partials.nav')

    <div class="max-w-7xl mx-auto px-4 py-6">
        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Graded Calls</h1>
            <p class="text-gray-600">Review calls you've graded</p>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-sm text-gray-500">Total Graded</p>
                <p class="text-2xl font-bold text-gray-900">{{ $stats['total_graded'] }}</p>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-sm text-gray-500">Average Score</p>
                <p class="text-2xl font-bold {{ $stats['avg_score'] >= 3 ? 'text-green-600' : ($stats['avg_score'] >= 2 ? 'text-blue-600' : 'text-orange-500') }}">
                    {{ number_format($stats['avg_score'], 2) }}/4
                </p>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-sm text-gray-500">This Week</p>
                <p class="text-2xl font-bold text-gray-900">{{ $stats['this_week'] }}</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow p-4 mb-6">
            <form method="GET" action="{{ route('manager.graded-calls') }}">
                <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
                    <select name="rep" class="border rounded px-3 py-2 text-sm">
                        <option value="">All Reps</option>
                        @foreach($reps as $rep)
                            <option value="{{ $rep->id }}" {{ request('rep') == $rep->id ? 'selected' : '' }}>
                                {{ $rep->name }}
                            </option>
                        @endforeach
                    </select>

                    <select name="project" class="border rounded px-3 py-2 text-sm">
                        <option value="">All Projects</option>
                        @foreach($projects as $project)
                            <option value="{{ $project->id }}" {{ request('project') == $project->id ? 'selected' : '' }}>
                                {{ $project->name }}
                            </option>
                        @endforeach
                    </select>

                    <select name="appointment_quality" class="border rounded px-3 py-2 text-sm">
                        <option value="">All Quality</option>
                        <option value="solid" {{ request('appointment_quality') == 'solid' ? 'selected' : '' }}>Solid</option>
                        <option value="tentative" {{ request('appointment_quality') == 'tentative' ? 'selected' : '' }}>Tentative</option>
                        <option value="backed_in" {{ request('appointment_quality') == 'backed_in' ? 'selected' : '' }}>Backed-in</option>
                    </select>

                    <input
                        type="date"
                        name="date_from"
                        value="{{ request('date_from') }}"
                        class="border rounded px-3 py-2 text-sm"
                        placeholder="From"
                    />

                    <input
                        type="date"
                        name="date_to"
                        value="{{ request('date_to') }}"
                        class="border rounded px-3 py-2 text-sm"
                        placeholder="To"
                    />

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
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rep</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Project</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Call Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Score</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Quality</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Graded</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($grades as $grade)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm text-gray-900">{{ $grade->call?->rep?->name ?? 'Unknown' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ $grade->call?->project?->name ?? 'Unknown' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ $grade->call?->called_at?->format('M j, Y') ?? '-' }}</td>
                            <td class="px-4 py-3">
                                @php
                                    $score = $grade->overall_score ?? 0;
                                    $colorClass = $score >= 3.5 ? 'text-green-600' : ($score >= 2.5 ? 'text-blue-600' : ($score >= 1.5 ? 'text-orange-500' : 'text-red-600'));
                                @endphp
                                <span class="text-sm font-medium {{ $colorClass }}">
                                    {{ number_format($score, 2) }}/4
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                @if($grade->appointment_quality)
                                    @php
                                        $qualityClass = [
                                            'solid' => 'bg-green-100 text-green-800',
                                            'tentative' => 'bg-yellow-100 text-yellow-800',
                                            'backed_in' => 'bg-orange-100 text-orange-800',
                                        ][$grade->appointment_quality] ?? 'bg-gray-100 text-gray-800';
                                        $qualityLabel = [
                                            'solid' => 'Solid',
                                            'tentative' => 'Tentative',
                                            'backed_in' => 'Backed-in',
                                        ][$grade->appointment_quality] ?? $grade->appointment_quality;
                                    @endphp
                                    <span class="text-xs px-2 py-0.5 rounded {{ $qualityClass }}">
                                        {{ $qualityLabel }}
                                    </span>
                                @else
                                    <span class="text-xs text-gray-400">-</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">{{ $grade->grading_completed_at?->format('M j, Y') ?? '-' }}</td>
                            <td class="px-4 py-3">
                                <a
                                    href="{{ route('manager.calls.grade', $grade->call_id) }}"
                                    class="text-blue-600 hover:text-blue-800 text-sm"
                                >
                                    View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                                No graded calls found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

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
