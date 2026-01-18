<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coaching Notes - Call Grader</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#f9fafb] min-h-screen">
    @include('manager.partials.nav')

    <div class="max-w-7xl mx-auto px-8 py-6">
        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-xl font-semibold text-gray-900">Coaching Notes</h1>
            <p class="text-sm text-gray-500">Browse all your coaching notes</p>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <p class="text-sm text-gray-500">Total Notes</p>
                <p class="text-2xl font-semibold text-gray-900">{{ $stats['total_notes'] }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <p class="text-sm text-gray-500">Overall Notes</p>
                <p class="text-2xl font-semibold text-purple-600">{{ $stats['overall_notes'] }}</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                <p class="text-sm text-gray-500">Objections</p>
                <p class="text-2xl font-semibold text-yellow-600">{{ $stats['objections'] }}</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
            <form method="GET" action="{{ route('manager.notes-library') }}">
                <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                    <select name="category" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">All Categories</option>
                        <option value="uncategorized" {{ request('category') == 'uncategorized' ? 'selected' : '' }}>Uncategorized</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" {{ request('category') == $cat->id ? 'selected' : '' }}>
                                {{ $cat->name }}
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

                    <select name="note_type" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        <option value="">All Note Types</option>
                        <option value="overall" {{ request('note_type') == 'overall' ? 'selected' : '' }}>Overall Notes</option>
                        <option value="snippet" {{ request('note_type') == 'snippet' ? 'selected' : '' }}>Snippet Notes</option>
                        <option value="objection" {{ request('note_type') == 'objection' ? 'selected' : '' }}>Objections</option>
                    </select>

                    <input
                        type="text"
                        name="search"
                        value="{{ request('search') }}"
                        class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        placeholder="Search notes..."
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

        <!-- Notes Grid -->
        <div class="grid gap-4">
            @forelse($notes as $note)
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 hover:shadow-md transition-shadow">
                    <div class="flex items-start justify-between mb-2">
                        <!-- Call info -->
                        <div class="text-sm text-gray-500">
                            <span class="font-medium text-gray-900">{{ $note->call?->rep?->name ?? 'Unknown Rep' }}</span>
                            <span class="mx-1">&bull;</span>
                            <span>{{ $note->call?->project?->name ?? 'Unknown Project' }}</span>
                            <span class="mx-1">&bull;</span>
                            <span>{{ $note->call?->called_at?->format('M j, Y') ?? '-' }}</span>
                        </div>

                        <!-- View call link -->
                        <a
                            href="{{ route('manager.calls.grade', $note->call_id) }}"
                            class="text-sm font-medium text-blue-600 hover:text-blue-700"
                        >
                            View Call &rarr;
                        </a>
                    </div>

                    <!-- Transcript excerpt or Overall Note label -->
                    @if($note->transcript_text)
                        <p class="text-sm text-gray-400 italic mb-2 line-clamp-2">
                            "{{ Str::limit($note->transcript_text, 150) }}"
                        </p>
                    @else
                        <p class="text-sm text-purple-600 font-medium mb-2 flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            Overall Call Note
                        </p>
                    @endif

                    <!-- Note text -->
                    <p class="text-gray-900 mb-3">{{ $note->note_text }}</p>

                    <!-- Tags -->
                    <div class="flex items-center gap-2 flex-wrap">
                        @if($note->transcript_text === null)
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-medium bg-purple-100 text-purple-700">
                                Overall
                            </span>
                        @elseif($note->category)
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-medium bg-blue-100 text-blue-700">
                                {{ $note->category->name }}
                            </span>
                        @else
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-medium bg-gray-100 text-gray-600">
                                Uncategorized
                            </span>
                        @endif

                        @if($note->is_objection)
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $note->objection_outcome === 'overcame' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                {{ $note->objectionType?->name ?? 'Objection' }}
                                {{ $note->objection_outcome === 'overcame' ? '✓' : '✗' }}
                            </span>
                        @endif

                        @if($note->timestamp_start)
                            <span class="text-xs text-gray-400 ml-auto">
                                {{ gmdate('i:s', (int)$note->timestamp_start) }}
                            </span>
                        @endif
                    </div>
                </div>
            @empty
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 text-center text-sm text-gray-500">
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
