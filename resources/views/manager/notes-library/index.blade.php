<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coaching Notes - Call Grader</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    @include('manager.partials.nav')

    <div class="max-w-7xl mx-auto px-4 py-6">
        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Coaching Notes</h1>
            <p class="text-gray-600">Browse all your coaching notes</p>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-sm text-gray-500">Total Notes</p>
                <p class="text-2xl font-bold text-gray-900">{{ $stats['total_notes'] }}</p>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-sm text-gray-500">Categorized</p>
                <p class="text-2xl font-bold text-blue-600">{{ $stats['with_category'] }}</p>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <p class="text-sm text-gray-500">Objections</p>
                <p class="text-2xl font-bold text-orange-600">{{ $stats['objections'] }}</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow p-4 mb-6">
            <form method="GET" action="{{ route('manager.notes-library') }}">
                <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                    <select name="category" class="border rounded px-3 py-2 text-sm">
                        <option value="">All Categories</option>
                        <option value="uncategorized" {{ request('category') == 'uncategorized' ? 'selected' : '' }}>Uncategorized</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" {{ request('category') == $cat->id ? 'selected' : '' }}>
                                {{ $cat->name }}
                            </option>
                        @endforeach
                    </select>

                    <select name="rep" class="border rounded px-3 py-2 text-sm">
                        <option value="">All Reps</option>
                        @foreach($reps as $rep)
                            <option value="{{ $rep->id }}" {{ request('rep') == $rep->id ? 'selected' : '' }}>
                                {{ $rep->name }}
                            </option>
                        @endforeach
                    </select>

                    <select name="is_objection" class="border rounded px-3 py-2 text-sm">
                        <option value="">All Notes</option>
                        <option value="true" {{ request('is_objection') == 'true' ? 'selected' : '' }}>Objections Only</option>
                        <option value="false" {{ request('is_objection') == 'false' ? 'selected' : '' }}>Non-Objections</option>
                    </select>

                    <input
                        type="text"
                        name="search"
                        value="{{ request('search') }}"
                        class="border rounded px-3 py-2 text-sm"
                        placeholder="Search notes..."
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

        <!-- Notes Grid -->
        <div class="grid gap-4">
            @forelse($notes as $note)
                <div class="bg-white rounded-lg shadow p-4 hover:shadow-md transition-shadow">
                    <div class="flex items-start justify-between mb-2">
                        <!-- Call info -->
                        <div class="text-sm text-gray-500">
                            <span class="font-medium text-gray-700">{{ $note->call?->rep?->name ?? 'Unknown Rep' }}</span>
                            &bull; {{ $note->call?->project?->name ?? 'Unknown Project' }}
                            &bull; {{ $note->call?->called_at?->format('M j, Y') ?? '-' }}
                        </div>

                        <!-- View call link -->
                        <a
                            href="{{ route('manager.calls.grade', $note->call_id) }}"
                            class="text-blue-600 hover:text-blue-800 text-sm"
                        >
                            View Call &rarr;
                        </a>
                    </div>

                    <!-- Transcript excerpt -->
                    <p class="text-sm text-gray-500 italic mb-2 line-clamp-2">
                        "{{ Str::limit($note->transcript_text, 150) }}"
                    </p>

                    <!-- Note text -->
                    <p class="text-gray-900 mb-3">{{ $note->note_text }}</p>

                    <!-- Tags -->
                    <div class="flex items-center gap-2 flex-wrap">
                        @if($note->category)
                            <span class="text-xs px-2 py-0.5 rounded bg-blue-100 text-blue-800">
                                {{ $note->category->name }}
                            </span>
                        @else
                            <span class="text-xs px-2 py-0.5 rounded bg-gray-100 text-gray-600">
                                Uncategorized
                            </span>
                        @endif

                        @if($note->is_objection)
                            <span class="text-xs px-2 py-0.5 rounded {{ $note->objection_outcome === 'overcame' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ $note->objectionType?->name ?? 'Objection' }}
                                {{ $note->objection_outcome === 'overcame' ? '✓' : '✗' }}
                            </span>
                        @endif

                        <span class="text-xs text-gray-400 ml-auto">
                            {{ gmdate('i:s', (int)$note->timestamp_start) }}
                        </span>
                    </div>
                </div>
            @empty
                <div class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
                    No notes found. Add notes while grading calls.
                </div>
            @endforelse
        </div>

        <!-- Pagination -->
        @if($notes->hasPages())
            <div class="mt-6 flex justify-center">
                {{ $notes->links() }}
            </div>
        @endif
    </div>
</body>
</html>
