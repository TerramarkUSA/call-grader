<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Golden Moments - Call Grader</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#f9fafb] min-h-screen">
    @include('manager.partials.nav')

    <div class="max-w-7xl mx-auto px-8 py-6">
        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-xl font-semibold text-gray-900">⭐ Golden Moments</h1>
            <p class="text-sm text-gray-500">Exemplar call moments worth replaying for training — shared across your office</p>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
            <form method="GET" action="{{ route('manager.golden-moments') }}">
                <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                    <select name="category" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500 focus:border-transparent">
                        <option value="">All Categories</option>
                        <option value="uncategorized" {{ request('category') == 'uncategorized' ? 'selected' : '' }}>Uncategorized</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" {{ request('category') == $cat->id ? 'selected' : '' }}>
                                {{ $cat->name }}
                            </option>
                        @endforeach
                    </select>

                    <select name="rep" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500 focus:border-transparent">
                        <option value="">All Reps</option>
                        @foreach($reps as $rep)
                            <option value="{{ $rep->id }}" {{ request('rep') == $rep->id ? 'selected' : '' }}>
                                {{ $rep->name }}
                            </option>
                        @endforeach
                    </select>

                    <select name="author" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500 focus:border-transparent">
                        <option value="">All Managers</option>
                        @foreach($authors as $author)
                            <option value="{{ $author->id }}" {{ request('author') == $author->id ? 'selected' : '' }}>
                                {{ $author->name }}
                            </option>
                        @endforeach
                    </select>

                    <input
                        type="text"
                        name="search"
                        value="{{ request('search') }}"
                        class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500 focus:border-transparent"
                        placeholder="Search moments..."
                    />

                    <button
                        type="submit"
                        class="bg-amber-500 text-white rounded-lg px-4 py-2 text-sm font-medium hover:bg-amber-600 transition-colors"
                    >
                        Apply Filters
                    </button>
                </div>
            </form>
        </div>

        <!-- Moments Grid -->
        <div class="grid gap-4">
            @forelse($moments as $moment)
                <div class="bg-white rounded-xl shadow-sm border border-amber-200 p-4 hover:shadow-md transition-shadow">
                    <div class="flex items-start justify-between mb-2">
                        <div class="text-sm text-gray-500">
                            <span class="font-medium text-gray-900">{{ $moment->call?->rep?->name ?? 'Unknown Rep' }}</span>
                            <span class="mx-1">&bull;</span>
                            <span>{{ $moment->call?->project?->name ?? 'Unknown Project' }}</span>
                            <span class="mx-1">&bull;</span>
                            <span>{{ $moment->call?->called_at?->format('M j, Y') ?? '-' }}</span>
                        </div>

                        <div class="flex items-center gap-3">
                            @if($moment->author_id === Auth::id())
                                <a href="{{ route('manager.calls.grade', $moment->call_id) }}" class="text-sm font-medium text-blue-600 hover:text-blue-700">
                                    Edit &rarr;
                                </a>
                            @endif
                            @if($moment->author_id === Auth::id() || Auth::user()->role === 'site_admin' || Auth::user()->role === 'system_admin')
                                <button
                                    onclick="deleteGoldenMoment({{ $moment->id }}, this)"
                                    class="text-sm font-medium text-red-500 hover:text-red-700"
                                >
                                    Delete
                                </button>
                            @endif
                            <a href="{{ route('manager.calls.grade', $moment->call_id) }}" class="text-sm font-medium text-amber-600 hover:text-amber-700">
                                View Call &rarr;
                            </a>
                        </div>
                    </div>

                    @if($moment->transcript_text)
                        <p class="text-sm text-gray-400 italic mb-2 line-clamp-2">
                            "{{ Str::limit($moment->transcript_text, 200) }}"
                        </p>
                    @endif

                    <p class="text-gray-900 mb-3">{{ $moment->note_text }}</p>

                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-medium bg-amber-100 text-amber-700">⭐ Golden Moment</span>

                        @if($moment->category)
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-medium bg-blue-100 text-blue-700">
                                {{ $moment->category->name }}
                            </span>
                        @endif

                        @if($moment->is_objection)
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $moment->objection_outcome === 'overcame' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                {{ $moment->objectionType?->name ?? 'Objection' }}
                                {{ $moment->objection_outcome === 'overcame' ? '✓' : '✗' }}
                            </span>
                        @endif

                        @if($moment->timestamp_start)
                            <span class="text-xs text-gray-400">
                                {{ gmdate('i:s', (int)$moment->timestamp_start) }}
                            </span>
                        @endif

                        <span class="text-xs text-gray-400 ml-auto">
                            by {{ $moment->author?->name ?? 'Unknown' }}
                            &bull;
                            {{ $moment->created_at->format('M j, Y') }}
                        </span>
                    </div>
                </div>
            @empty
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 text-center">
                    <p class="text-sm text-gray-500">No golden moments yet.</p>
                    <p class="text-xs text-gray-400 mt-1">Mark a coaching note as a Golden Moment while grading to share it here.</p>
                </div>
            @endforelse
        </div>

        @if($moments->hasPages())
            <div class="mt-6 flex justify-center">
                {{ $moments->links() }}
            </div>
        @endif
    </div>

    <script>
        async function deleteGoldenMoment(noteId, btn) {
            if (!confirm('Delete this golden moment? This cannot be undone.')) return;

            btn.disabled = true;
            btn.textContent = 'Deleting...';

            try {
                const response = await fetch(`/manager/notes/${noteId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                });

                if (!response.ok) throw new Error('Failed to delete');

                btn.closest('.bg-white').remove();
            } catch (error) {
                alert('Failed to delete. Please try again.');
                btn.disabled = false;
                btn.textContent = 'Delete';
            }
        }
    </script>
</body>
</html>
