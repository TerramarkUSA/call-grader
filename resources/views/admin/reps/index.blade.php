<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reps Management - Call Grader</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body class="bg-gray-100 min-h-screen">
    @include('admin.partials.nav')

    <div class="max-w-4xl mx-auto px-4 py-6">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Reps Management</h1>
            <p class="text-gray-600">Manage sales representatives for each office</p>
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

        <!-- Office Selector -->
        <div class="bg-white rounded-lg shadow p-4 mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">Select Office</label>
            <form method="GET" action="{{ route('admin.reps.index') }}" id="office-selector-form">
                <select
                    name="account_id"
                    class="w-full border rounded px-3 py-2 text-sm"
                    onchange="document.getElementById('office-selector-form').submit()"
                >
                    @foreach($accounts as $account)
                        <option value="{{ $account->id }}" {{ $selectedAccountId == $account->id ? 'selected' : '' }}>
                            {{ $account->name }}
                        </option>
                    @endforeach
                </select>
            </form>
        </div>

        <!-- Add New Rep Form -->
        <div class="bg-white rounded-lg shadow p-4 mb-6">
            <h3 class="font-medium text-gray-900 mb-3">Add New Rep</h3>
            <form method="POST" action="{{ route('admin.reps.store') }}">
                @csrf
                <input type="hidden" name="account_id" value="{{ $selectedAccountId }}">
                <div class="flex gap-4">
                    <div class="flex-1">
                        <input
                            type="text"
                            name="name"
                            class="w-full border rounded px-3 py-2 text-sm"
                            placeholder="Rep name"
                            required
                        />
                    </div>
                    <div class="w-48">
                        <input
                            type="text"
                            name="external_id"
                            class="w-full border rounded px-3 py-2 text-sm"
                            placeholder="External ID (optional)"
                        />
                    </div>
                    <button
                        type="submit"
                        class="px-4 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700"
                    >
                        Add Rep
                    </button>
                </div>
            </form>
        </div>

        <!-- Reps List -->
        <div class="bg-white rounded-lg shadow">
            @if($reps->isEmpty())
                <div class="p-8 text-center text-gray-500">
                    No reps configured for this office yet. Add one above to get started.
                </div>
            @else
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">External ID</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Calls</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($reps as $rep)
                            <tr>
                                <form method="POST" action="{{ route('admin.reps.update', $rep) }}" class="contents" id="form-{{ $rep->id }}">
                                    @csrf
                                    @method('PATCH')
                                    <td class="px-4 py-3">
                                        <input
                                            type="text"
                                            name="name"
                                            value="{{ $rep->name }}"
                                            class="w-full border-b border-transparent hover:border-gray-300 focus:border-blue-500 focus:outline-none bg-transparent text-sm"
                                        />
                                    </td>
                                    <td class="px-4 py-3">
                                        <input
                                            type="text"
                                            name="external_id"
                                            value="{{ $rep->external_id }}"
                                            class="w-full border-b border-transparent hover:border-gray-300 focus:border-blue-500 focus:outline-none bg-transparent text-sm text-gray-500"
                                            placeholder="—"
                                        />
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-500">
                                        {{ $rep->calls_count }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <label class="flex items-center gap-1 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                name="is_active"
                                                value="1"
                                                {{ $rep->is_active ? 'checked' : '' }}
                                                class="rounded"
                                            />
                                            <span class="text-xs {{ $rep->is_active ? 'text-green-600' : 'text-gray-400' }}">
                                                {{ $rep->is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                        </label>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <button
                                            type="submit"
                                            class="px-3 py-1 bg-gray-100 text-gray-700 text-xs rounded hover:bg-gray-200"
                                        >
                                            Save
                                        </button>
                                </form>
                                        @if($rep->calls_count === 0)
                                            <form method="POST" action="{{ route('admin.reps.destroy', $rep) }}" class="inline ml-2">
                                                @csrf
                                                @method('DELETE')
                                                <button
                                                    type="submit"
                                                    class="px-3 py-1 text-red-600 text-xs hover:text-red-800"
                                                    onclick="return confirm('Delete this rep?')"
                                                >
                                                    Delete
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <p class="mt-6 text-sm text-gray-500">
            Note: Reps are automatically matched to incoming calls when the rep name from CTM matches.
            External ID can be used for integration with other systems like Salesforce.
        </p>
    </div>
</body>
</html>
