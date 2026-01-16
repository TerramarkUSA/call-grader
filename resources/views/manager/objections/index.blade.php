<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Objections - Call Grader</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    @include('manager.partials.nav')

    <div class="max-w-7xl mx-auto px-4 py-6">
        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Objections</h1>
            <p class="text-gray-600">Track objections and outcomes</p>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-sm text-gray-500">Total Objections</p>
                <p class="text-2xl font-bold text-gray-900">{{ $stats['total'] }}</p>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-sm text-gray-500">Overcame</p>
                <p class="text-2xl font-bold text-green-600">
                    {{ $stats['overcame'] }}
                    @if($stats['total'] > 0)
                        <span class="text-sm font-normal text-gray-500">
                            ({{ round(($stats['overcame'] / $stats['total']) * 100) }}%)
                        </span>
                    @endif
                </p>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-sm text-gray-500">Failed</p>
                <p class="text-2xl font-bold text-red-600">
                    {{ $stats['failed'] }}
                    @if($stats['total'] > 0)
                        <span class="text-sm font-normal text-gray-500">
                            ({{ round(($stats['failed'] / $stats['total']) * 100) }}%)
                        </span>
                    @endif
                </p>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow p-4 mb-6">
            <form method="GET" action="{{ route('manager.objections') }}">
                <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                    <select name="objection_type" class="border rounded px-3 py-2 text-sm">
                        <option value="">All Types</option>
                        @foreach($objectionTypes as $type)
                            <option value="{{ $type->id }}" {{ request('objection_type') == $type->id ? 'selected' : '' }}>
                                {{ $type->name }}
                            </option>
                        @endforeach
                    </select>

                    <select name="outcome" class="border rounded px-3 py-2 text-sm">
                        <option value="">All Outcomes</option>
                        <option value="overcame" {{ request('outcome') == 'overcame' ? 'selected' : '' }}>Overcame</option>
                        <option value="failed" {{ request('outcome') == 'failed' ? 'selected' : '' }}>Failed</option>
                    </select>

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

                    <button
                        type="submit"
                        class="bg-blue-600 text-white rounded px-4 py-2 text-sm hover:bg-blue-700"
                    >
                        Apply
                    </button>
                </div>
            </form>
        </div>

        <!-- Objections List -->
        <div class="space-y-4">
            @forelse($objections as $objection)
                <div class="bg-white rounded-lg shadow p-4 border-l-4 {{ $objection->objection_outcome === 'overcame' ? 'border-green-500' : 'border-red-500' }}">
                    <div class="flex items-start justify-between mb-2">
                        <!-- Objection type badge -->
                        <span class="text-sm font-medium px-2 py-0.5 rounded {{ $objection->objection_outcome === 'overcame' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                            {{ $objection->objectionType?->name ?? 'Unknown' }}
                            {{ $objection->objection_outcome === 'overcame' ? '✓ Overcame' : '✗ Failed' }}
                        </span>

                        <!-- Call info -->
                        <div class="text-sm text-gray-500 text-right">
                            <span class="font-medium text-gray-700">{{ $objection->call?->rep?->name ?? 'Unknown Rep' }}</span>
                            <br />
                            {{ $objection->call?->project?->name ?? 'Unknown Project' }} &bull; {{ $objection->call?->called_at?->format('M j, Y') ?? '-' }}
                        </div>
                    </div>

                    <!-- Transcript excerpt -->
                    <p class="text-sm text-gray-500 italic mb-2">
                        "{{ Str::limit($objection->transcript_text, 200) }}"
                    </p>

                    <!-- Coaching note -->
                    <p class="text-gray-900 mb-3">{{ $objection->note_text }}</p>

                    <!-- Footer -->
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-gray-400">
                            {{ gmdate('i:s', (int)$objection->timestamp_start) }}
                        </span>
                        <a
                            href="{{ route('manager.calls.grade', $objection->call_id) }}"
                            class="text-blue-600 hover:text-blue-800 text-sm"
                        >
                            View Call &rarr;
                        </a>
                    </div>
                </div>
            @empty
                <div class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
                    No objections recorded yet. Flag objections while grading calls.
                </div>
            @endforelse
        </div>

        <!-- Pagination -->
        @if($objections->hasPages())
            <div class="mt-6 flex justify-center">
                {{ $objections->links() }}
            </div>
        @endif
    </div>
</body>
</html>
