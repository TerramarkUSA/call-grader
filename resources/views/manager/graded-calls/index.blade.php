<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Graded Calls - Call Grader</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#f9fafb] min-h-screen">
    @include('manager.partials.nav')

    <div class="max-w-7xl mx-auto px-8 py-6">
        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-xl font-semibold text-gray-900">Graded Calls</h1>
            <p class="text-sm text-gray-500">All graded calls across your office</p>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <p class="text-sm text-gray-500">Total Graded</p>
                <p class="text-2xl font-semibold text-gray-900">{{ $stats['total_graded'] }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <p class="text-sm text-gray-500">Average Score</p>
                <p class="text-2xl font-semibold {{ $stats['avg_score'] >= 3 ? 'text-green-600' : ($stats['avg_score'] >= 2 ? 'text-blue-600' : 'text-yellow-600') }}">
                    {{ number_format($stats['avg_score'], 2) }}/4
                </p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <p class="text-sm text-gray-500">This Week</p>
                <p class="text-2xl font-semibold text-gray-900">{{ $stats['this_week'] }}</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
            <form method="GET" action="{{ route('manager.graded-calls') }}">
                <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-4">
                    <select name="grader" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">All Graders</option>
                        @foreach($graders as $grader)
                            <option value="{{ $grader->id }}" {{ request('grader') == $grader->id ? 'selected' : '' }}>
                                {{ $grader->name }}
                            </option>
                        @endforeach
                    </select>

                    <select name="rep" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">All Reps</option>
                        @foreach($reps as $rep)
                            <option value="{{ $rep->id }}" {{ request('rep') == $rep->id ? 'selected' : '' }}>
                                {{ $rep->name }}
                            </option>
                        @endforeach
                    </select>

                    <select name="project" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">All Projects</option>
                        @foreach($projects as $project)
                            <option value="{{ $project->id }}" {{ request('project') == $project->id ? 'selected' : '' }}>
                                {{ $project->name }}
                            </option>
                        @endforeach
                    </select>

                    <select name="appointment_quality" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">All Quality</option>
                        <option value="solid" {{ request('appointment_quality') == 'solid' ? 'selected' : '' }}>Solid</option>
                        <option value="tentative" {{ request('appointment_quality') == 'tentative' ? 'selected' : '' }}>Tentative</option>
                        <option value="backed_in" {{ request('appointment_quality') == 'backed_in' ? 'selected' : '' }}>Backed-in</option>
                    </select>

                    <input
                        type="date"
                        name="date_from"
                        value="{{ request('date_from') }}"
                        class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        placeholder="From"
                    />

                    <input
                        type="date"
                        name="date_to"
                        value="{{ request('date_to') }}"
                        class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        placeholder="To"
                    />

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
            <table class="min-w-full">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Rep</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Project</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Call Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Length</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Score</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Quality</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Graded By</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Graded</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($grades as $grade)
                        <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                            <td class="px-4 py-4 text-sm font-medium text-gray-900">{{ $grade->call?->rep?->name ?? 'Unknown' }}</td>
                            <td class="px-4 py-4 text-sm text-gray-500">{{ $grade->call?->project?->name ?? 'Unknown' }}</td>
                            <td class="px-4 py-4 text-sm text-gray-500">{{ $grade->call?->called_at?->format('M j, Y') ?? '-' }}</td>
                            <td class="px-4 py-4 text-sm text-gray-500">
                                @if($grade->call?->talk_time)
                                    {{ floor($grade->call->talk_time / 60) }}:{{ str_pad($grade->call->talk_time % 60, 2, '0', STR_PAD_LEFT) }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-4">
                                @php
                                    $score = $grade->overall_score ?? 0;
                                    $colorClass = $score >= 3.5 ? 'text-green-600' : ($score >= 2.5 ? 'text-blue-600' : ($score >= 1.5 ? 'text-yellow-600' : 'text-red-600'));
                                @endphp
                                <span class="text-sm font-medium {{ $colorClass }}">
                                    {{ number_format($score, 2) }}/4
                                </span>
                            </td>
                            <td class="px-4 py-4">
                                @if($grade->appointment_quality)
                                    @php
                                        $qualityClass = [
                                            'solid' => 'bg-green-100 text-green-700',
                                            'tentative' => 'bg-yellow-100 text-yellow-700',
                                            'backed_in' => 'bg-orange-100 text-orange-700',
                                        ][$grade->appointment_quality] ?? 'bg-gray-100 text-gray-700';
                                        $qualityLabel = [
                                            'solid' => 'Solid',
                                            'tentative' => 'Tentative',
                                            'backed_in' => 'Backed-in',
                                        ][$grade->appointment_quality] ?? $grade->appointment_quality;
                                    @endphp
                                    <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $qualityClass }}">
                                        {{ $qualityLabel }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-4 text-sm text-gray-500">{{ $grade->gradedBy?->name ?? 'Unknown' }}</td>
                            <td class="px-4 py-4 text-sm text-gray-500">{{ $grade->grading_completed_at?->format('M j, Y') ?? '-' }}</td>
                            <td class="px-4 py-4">
                                <a
                                    href="{{ route('manager.calls.grade', $grade->call_id) }}"
                                    class="text-sm font-medium text-blue-600 hover:text-blue-700"
                                >
                                    View &rarr;
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-8 text-center text-sm text-gray-500">
                                No graded calls found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <!-- Pagination -->
            @if($grades->hasPages())
                <div class="px-4 py-3 border-t border-gray-100 flex justify-between items-center">
                    <p class="text-sm text-gray-500">
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
