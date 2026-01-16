<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rubric Categories - Call Grader</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body class="bg-gray-100 min-h-screen">
    @include('admin.partials.nav')

    <div class="max-w-4xl mx-auto px-4 py-6">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Rubric Categories</h1>
            <p class="text-gray-600">Configure the grading categories and weights</p>
            <div class="mt-2 flex gap-4 text-sm">
                <a href="{{ route('admin.rubric.categories') }}" class="text-blue-600 font-medium">Categories</a>
                <a href="{{ route('admin.rubric.checkpoints') }}" class="text-gray-500 hover:text-gray-700">Checkpoints</a>
            </div>
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

        <!-- Total Weight Warning -->
        @php
            $totalWeight = $categories->sum('weight');
        @endphp
        @if($totalWeight != 100)
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                <p class="text-yellow-800">
                    Warning: Category weights total <strong>{{ $totalWeight }}%</strong>. They should total 100%.
                </p>
            </div>
        @endif

        <!-- Categories List -->
        <div class="space-y-4">
            @foreach($categories as $category)
                <div class="bg-white rounded-lg shadow">
                    <form method="POST" action="{{ route('admin.rubric.categories.update', $category) }}" class="p-4">
                        @csrf
                        @method('PATCH')

                        <div class="flex items-start justify-between mb-3">
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <input
                                        type="text"
                                        name="name"
                                        value="{{ $category->name }}"
                                        class="text-lg font-medium border-b border-transparent hover:border-gray-300 focus:border-blue-500 focus:outline-none bg-transparent"
                                    />
                                    <span class="text-xs px-2 py-0.5 rounded {{ $category->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                                        {{ $category->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <label class="text-sm text-gray-600">Weight:</label>
                                <input
                                    type="number"
                                    name="weight"
                                    value="{{ intval($category->weight) }}"
                                    class="w-16 border rounded px-2 py-1 text-center"
                                    min="1"
                                    max="100"
                                />
                                <span class="text-sm text-gray-600">%</span>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="block text-xs text-gray-500 mb-1">Description</label>
                            <textarea
                                name="description"
                                rows="2"
                                class="w-full border rounded px-3 py-2 text-sm"
                                placeholder="What should managers look for in this category?"
                            >{{ $category->description }}</textarea>
                        </div>

                        <div class="flex items-center justify-between">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input
                                    type="checkbox"
                                    name="is_active"
                                    value="1"
                                    {{ $category->is_active ? 'checked' : '' }}
                                    class="rounded"
                                />
                                <span class="text-sm text-gray-600">Active</span>
                            </label>
                            <button
                                type="submit"
                                class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700"
                            >
                                Save
                            </button>
                        </div>
                    </form>
                </div>
            @endforeach
        </div>

        <p class="mt-6 text-sm text-gray-500">
            Note: Categories cannot be added or deleted to maintain data integrity. Contact support if you need structural changes.
        </p>
    </div>
</body>
</html>
