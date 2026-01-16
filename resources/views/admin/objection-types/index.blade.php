<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Objection Types - Call Grader</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body class="bg-gray-100 min-h-screen">
    @include('admin.partials.nav')

    <div class="max-w-3xl mx-auto px-4 py-6">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Objection Types</h1>
            <p class="text-gray-600">Manage common objections that reps encounter on calls</p>
        </div>

        @if (session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                {{ session('error') }}
            </div>
        @endif

        <!-- Add New Objection Type Form -->
        <div class="bg-white rounded-lg shadow p-4 mb-6">
            <h3 class="font-medium text-gray-900 mb-3">Add New Objection Type</h3>
            <form method="POST" action="{{ route('admin.objection-types.store') }}">
                @csrf
                <div class="flex gap-4">
                    <div class="flex-1">
                        <input
                            type="text"
                            name="name"
                            class="w-full border rounded px-3 py-2 text-sm"
                            placeholder="e.g., Price concern"
                            required
                        />
                    </div>
                    <button
                        type="submit"
                        class="px-4 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700"
                    >
                        Add Objection Type
                    </button>
                </div>
            </form>
        </div>

        <!-- Objection Types List -->
        <div class="bg-white rounded-lg shadow">
            @if($objectionTypes->isEmpty())
                <div class="p-8 text-center text-gray-500">
                    No objection types configured yet. Add one above to get started.
                </div>
            @else
                <div class="divide-y">
                    @foreach($objectionTypes as $type)
                        <div class="p-4">
                            <form method="POST" action="{{ route('admin.objection-types.update', $type) }}" class="flex items-center gap-4">
                                @csrf
                                @method('PATCH')

                                <div class="flex-1">
                                    <input
                                        type="text"
                                        name="name"
                                        value="{{ $type->name }}"
                                        class="w-full border-b border-transparent hover:border-gray-300 focus:border-blue-500 focus:outline-none bg-transparent"
                                    />
                                </div>

                                <div class="flex items-center gap-2">
                                    <label class="flex items-center gap-1 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            name="is_active"
                                            value="1"
                                            {{ $type->is_active ? 'checked' : '' }}
                                            class="rounded"
                                        />
                                        <span class="text-xs text-gray-600">Active</span>
                                    </label>
                                </div>

                                <button
                                    type="submit"
                                    class="px-3 py-1 bg-gray-100 text-gray-700 text-xs rounded hover:bg-gray-200"
                                >
                                    Save
                                </button>
                            </form>

                            <div class="mt-2 flex items-center justify-between">
                                <span class="text-xs text-gray-500">
                                    Used in {{ $type->grades_count ?? 0 }} grades
                                </span>
                                @if(($type->grades_count ?? 0) === 0)
                                    <form method="POST" action="{{ route('admin.objection-types.destroy', $type) }}" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button
                                            type="submit"
                                            class="text-xs text-red-600 hover:text-red-800"
                                            onclick="return confirm('Delete this objection type?')"
                                        >
                                            Delete
                                        </button>
                                    </form>
                                @else
                                    <span class="text-xs text-gray-400">Cannot delete (in use)</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <p class="mt-6 text-sm text-gray-500">
            Note: Objection types help categorize the types of pushback reps receive during calls.
            Active types will appear in the grading form.
        </p>
    </div>
</body>
</html>
