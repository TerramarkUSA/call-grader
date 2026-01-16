<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Offices - Call Grader</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    @include('admin.partials.nav')

    <div class="max-w-7xl mx-auto px-4 py-6">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Offices</h1>
                <p class="text-gray-600">Manage CTM account connections</p>
            </div>
            <a href="{{ route('admin.accounts.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                + Add Office
            </a>
        </div>

        @if (session('success'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                {{ session('success') }}
            </div>
        @endif

        @if (session('warning'))
            <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-4">
                {{ session('warning') }}
            </div>
        @endif

        @if (session('error'))
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                {{ session('error') }}
            </div>
        @endif

        @if ($accounts->isEmpty())
            <div class="bg-white rounded-lg shadow p-8 text-center">
                <h3 class="text-lg font-medium text-gray-900 mb-2">No offices connected yet</h3>
                <p class="text-gray-600 mb-4">Connect your first CTM account to start pulling calls.</p>
                <a href="{{ route('admin.accounts.create') }}" class="inline-block bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                    Connect CTM Account
                </a>
            </div>
        @else
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Office Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">CTM Account</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Calls</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Last Sync</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach ($accounts as $account)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap font-medium">{{ $account->name }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-gray-500">{{ $account->ctm_account_id }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">{{ number_format($account->calls_count) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-gray-500">
                                    {{ $account->last_sync_at ? $account->last_sync_at->diffForHumans() : 'Never' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if ($account->is_active)
                                        <span class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded">Active</span>
                                    @else
                                        <span class="px-2 py-1 text-xs bg-red-100 text-red-800 rounded">Inactive</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm space-x-2">
                                    <form method="POST" action="{{ route('admin.accounts.test-connection', $account) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-blue-600 hover:text-blue-900">Test</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.accounts.sync-calls', $account) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-green-600 hover:text-green-900">Sync</button>
                                    </form>
                                    <a href="{{ route('admin.accounts.edit', $account) }}" class="text-gray-600 hover:text-gray-900">Edit</a>
                                    <form method="POST" action="{{ route('admin.accounts.toggle-active', $account) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-orange-600 hover:text-orange-900">
                                            {{ $account->is_active ? 'Disable' : 'Enable' }}
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</body>
</html>
