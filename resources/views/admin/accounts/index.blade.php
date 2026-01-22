<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Offices - Call Grader</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#f9fafb] min-h-screen">
    @include('admin.partials.nav')

    <div class="max-w-7xl mx-auto px-8 py-6">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-xl font-semibold text-gray-900">Offices</h1>
                <p class="text-sm text-gray-500">Manage CTM and Salesforce connections</p>
            </div>
            <a href="{{ route('admin.accounts.create') }}" class="bg-blue-600 text-white px-4 py-2 rounded-lg font-medium hover:bg-blue-700 transition-colors">
                + Add Office
            </a>
        </div>

        @if (session('success'))
            <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl mb-4">
                {{ session('success') }}
            </div>
        @endif

        @if (session('warning'))
            <div class="bg-yellow-50 border border-yellow-200 text-yellow-700 px-4 py-3 rounded-xl mb-4">
                {{ session('warning') }}
            </div>
        @endif

        @if (session('error'))
            <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-4">
                {{ session('error') }}
            </div>
        @endif

        @if (session('info'))
            <div class="bg-blue-50 border border-blue-200 text-blue-700 px-4 py-3 rounded-xl mb-4">
                {{ session('info') }}
            </div>
        @endif

        @if ($accounts->isEmpty())
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 text-center">
                <h3 class="text-lg font-medium text-gray-900 mb-2">No offices connected yet</h3>
                <p class="text-sm text-gray-500 mb-4">Connect your first CTM account to start pulling calls.</p>
                <a href="{{ route('admin.accounts.create') }}" class="inline-block bg-blue-600 text-white px-4 py-2 rounded-lg font-medium hover:bg-blue-700 transition-colors">
                    Connect CTM Account
                </a>
            </div>
        @else
            <!-- CTM Table -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h2 class="text-lg font-semibold text-gray-900">CTM Accounts</h2>
                    <p class="text-sm text-gray-500">Call tracking connections</p>
                </div>
                <table class="min-w-full">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Office Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">CTM Account</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Calls</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Last Sync</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($accounts as $account)
                            <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $account->name }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $account->ctm_account_id }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ number_format($account->calls_count) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $account->last_sync_at ? $account->last_sync_at->diffForHumans() : 'Never' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if ($account->is_active)
                                        <span class="rounded-full px-2.5 py-0.5 text-xs font-medium bg-green-100 text-green-700">Active</span>
                                    @else
                                        <span class="rounded-full px-2.5 py-0.5 text-xs font-medium bg-red-100 text-red-700">Inactive</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm space-x-3">
                                    <form method="POST" action="{{ route('admin.accounts.test-connection', $account) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="font-medium text-blue-600 hover:text-blue-700 transition-colors">Test</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.accounts.sync-calls', $account) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="font-medium text-green-600 hover:text-green-700 transition-colors">Sync</button>
                                    </form>
                                    <a href="{{ route('admin.accounts.edit', $account) }}" class="font-medium text-gray-600 hover:text-gray-700 transition-colors">Edit</a>
                                    <form method="POST" action="{{ route('admin.accounts.toggle-active', $account) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="font-medium text-yellow-600 hover:text-yellow-700 transition-colors">
                                            {{ $account->is_active ? 'Disable' : 'Enable' }}
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Salesforce Table -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-900">Salesforce Enrichment</h2>
                        <p class="text-sm text-gray-500">
                            @if($sfConnected)
                                <span class="text-green-600">● Connected</span> — Enrich calls with Chance data
                            @else
                                <span class="text-gray-400">● Not connected</span> — <a href="{{ route('admin.salesforce.index') }}" class="text-blue-600 hover:text-blue-700">Configure in Settings</a>
                            @endif
                        </p>
                    </div>
                </div>
                <form method="POST" action="{{ route('admin.accounts.office-mappings') }}">
                    @csrf
                    <table class="min-w-full">
                        <thead class="bg-gray-50 border-b border-gray-100">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Office</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">SF Office Value</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Enriched</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Pending</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($accounts as $index => $account)
                                <tr class="border-b border-gray-100 hover:bg-gray-50 transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $account->name }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <input type="hidden" name="mappings[{{ $index }}][account_id]" value="{{ $account->id }}">
                                        <input
                                            type="text"
                                            name="mappings[{{ $index }}][sf_office_name]"
                                            value="{{ $account->sf_office_name }}"
                                            placeholder="e.g., Dallas"
                                            class="border border-gray-200 rounded-lg px-2 py-1 text-sm w-40 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                        >
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <span class="text-green-600 font-medium">{{ number_format($account->sf_enriched_count) }}</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        @if($account->sf_pending_count > 0)
                                            <span class="text-yellow-600 font-medium">{{ number_format($account->sf_pending_count) }}</span>
                                        @else
                                            <span class="text-gray-400">0</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        @if($sfConnected && $account->sf_pending_count > 0)
                                            <button type="button" onclick="syncSalesforce({{ $account->id }})" class="font-medium text-purple-600 hover:text-purple-700 transition-colors">
                                                Sync SF
                                            </button>
                                        @elseif($sfConnected)
                                            <span class="text-gray-400">Up to date</span>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="px-6 py-4 border-t border-gray-100 bg-gray-50">
                        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">
                            Save Office Mappings
                        </button>
                    </div>
                </form>
            </div>
        @endif
    </div>

    <script>
        function syncSalesforce(accountId) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = `/admin/accounts/${accountId}/sync-salesforce`;
            
            const csrf = document.createElement('input');
            csrf.type = 'hidden';
            csrf.name = '_token';
            csrf.value = '{{ csrf_token() }}';
            form.appendChild(csrf);
            
            document.body.appendChild(form);
            form.submit();
        }
    </script>
</body>
</html>
