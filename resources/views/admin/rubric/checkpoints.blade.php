<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rubric Checkpoints - Call Grader</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body class="bg-[#f9fafb] min-h-screen">
    @include('admin.partials.nav')

    <div class="max-w-4xl mx-auto px-8 py-6">
        <div class="mb-6">
            <h1 class="text-xl font-semibold text-gray-900">Rubric Checkpoints</h1>
            <p class="text-sm text-gray-500">Configure the scoring checkpoints for call grading</p>
            <div class="mt-3 flex gap-4 text-sm">
                <a href="{{ route('admin.rubric.categories') }}" class="text-gray-500 hover:text-gray-700 transition-colors">Categories</a>
                <a href="{{ route('admin.rubric.checkpoints') }}" class="font-medium text-blue-600">Checkpoints</a>
            </div>
        </div>

        @if (session('success'))
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl mb-4">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-4">
                {{ session('error') }}
            </div>
        @endif

        <!-- Add New Checkpoint Form -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
            <h3 class="font-medium text-gray-900 mb-3">Add New Checkpoint</h3>
            <form method="POST" action="{{ route('admin.rubric.checkpoints.store') }}">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-xs text-gray-500 mb-1">Name</label>
                        <input
                            type="text"
                            name="name"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            placeholder="e.g., Proper greeting and introduction"
                            required
                        />
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Type</label>
                        <select name="type" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                            <option value="positive">Positive (+)</option>
                            <option value="negative">Negative (-)</option>
                        </select>
                    </div>
                </div>
                <div class="mt-3">
                    <button
                        type="submit"
                        class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors"
                    >
                        Add Checkpoint
                    </button>
                </div>
            </form>
        </div>

        @php
            $positiveCheckpoints = $checkpoints->where('type', 'positive')->sortBy('sort_order');
            $negativeCheckpoints = $checkpoints->where('type', 'negative')->sortBy('sort_order');
        @endphp

        <!-- Positive Checkpoints -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 mb-6">
            <div class="px-4 py-3 border-b border-gray-100 bg-green-50 rounded-t-xl">
                <div class="flex items-center justify-between">
                    <h3 class="font-medium text-green-900">Positive Checkpoints (+)</h3>
                    <span class="text-sm text-green-700">{{ $positiveCheckpoints->count() }} checkpoints</span>
                </div>
                <p class="text-xs text-green-600 mt-1">These add to the score when checked</p>
            </div>

            @if($positiveCheckpoints->isEmpty())
                <div class="p-4 text-center text-sm text-gray-500">
                    No positive checkpoints configured yet.
                </div>
            @else
                <div class="divide-y divide-gray-100">
                    @foreach($positiveCheckpoints as $checkpoint)
                        <div class="p-4">
                            <form method="POST" action="{{ route('admin.rubric.checkpoints.update', $checkpoint) }}" class="flex items-center gap-4">
                                @csrf
                                @method('PATCH')

                                <div class="flex-1">
                                    <input
                                        type="text"
                                        name="name"
                                        value="{{ $checkpoint->name }}"
                                        class="w-full border-b border-transparent hover:border-gray-300 focus:border-blue-500 focus:outline-none bg-transparent text-sm"
                                    />
                                </div>

                                <div class="flex items-center gap-2">
                                    <label class="flex items-center gap-1 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            name="is_active"
                                            value="1"
                                            {{ $checkpoint->is_active ? 'checked' : '' }}
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                        />
                                        <span class="text-xs text-gray-600">Active</span>
                                    </label>
                                </div>

                                <button
                                    type="submit"
                                    class="px-3 py-1 bg-gray-100 text-gray-700 text-xs font-medium rounded-lg hover:bg-gray-200 transition-colors"
                                >
                                    Save
                                </button>
                            </form>

                            <div class="mt-2 flex items-center justify-between">
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $checkpoint->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                                    {{ $checkpoint->is_active ? 'Active' : 'Inactive' }}
                                </span>
                                <form method="POST" action="{{ route('admin.rubric.checkpoints.delete', $checkpoint) }}" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button
                                        type="submit"
                                        class="text-xs font-medium text-red-600 hover:text-red-700 transition-colors"
                                        onclick="return confirm('Delete this checkpoint? This may affect existing grades.')"
                                    >
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Negative Checkpoints -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200">
            <div class="px-4 py-3 border-b border-gray-100 bg-red-50 rounded-t-xl">
                <div class="flex items-center justify-between">
                    <h3 class="font-medium text-red-900">Negative Checkpoints (-)</h3>
                    <span class="text-sm text-red-700">{{ $negativeCheckpoints->count() }} checkpoints</span>
                </div>
                <p class="text-xs text-red-600 mt-1">These reduce the score when checked</p>
            </div>

            @if($negativeCheckpoints->isEmpty())
                <div class="p-4 text-center text-sm text-gray-500">
                    No negative checkpoints configured yet.
                </div>
            @else
                <div class="divide-y divide-gray-100">
                    @foreach($negativeCheckpoints as $checkpoint)
                        <div class="p-4">
                            <form method="POST" action="{{ route('admin.rubric.checkpoints.update', $checkpoint) }}" class="flex items-center gap-4">
                                @csrf
                                @method('PATCH')

                                <div class="flex-1">
                                    <input
                                        type="text"
                                        name="name"
                                        value="{{ $checkpoint->name }}"
                                        class="w-full border-b border-transparent hover:border-gray-300 focus:border-blue-500 focus:outline-none bg-transparent text-sm"
                                    />
                                </div>

                                <div class="flex items-center gap-2">
                                    <label class="flex items-center gap-1 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            name="is_active"
                                            value="1"
                                            {{ $checkpoint->is_active ? 'checked' : '' }}
                                            class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                        />
                                        <span class="text-xs text-gray-600">Active</span>
                                    </label>
                                </div>

                                <button
                                    type="submit"
                                    class="px-3 py-1 bg-gray-100 text-gray-700 text-xs font-medium rounded-lg hover:bg-gray-200 transition-colors"
                                >
                                    Save
                                </button>
                            </form>

                            <div class="mt-2 flex items-center justify-between">
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $checkpoint->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                                    {{ $checkpoint->is_active ? 'Active' : 'Inactive' }}
                                </span>
                                <form method="POST" action="{{ route('admin.rubric.checkpoints.delete', $checkpoint) }}" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button
                                        type="submit"
                                        class="text-xs font-medium text-red-600 hover:text-red-700 transition-colors"
                                        onclick="return confirm('Delete this checkpoint? This may affect existing grades.')"
                                    >
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <p class="mt-6 text-sm text-gray-500">
            Note: Positive checkpoints add to the score when checked. Negative checkpoints reduce the score when checked.
            Inactive checkpoints will not appear in the grading form.
        </p>
    </div>
</body>
</html>
