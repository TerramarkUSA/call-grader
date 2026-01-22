<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salesforce Settings - Call Grader</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body class="bg-[#f9fafb] min-h-screen">
    @include('admin.partials.nav')

    <div class="max-w-5xl mx-auto px-8 py-6">
        <div class="mb-6">
            <div class="flex items-center gap-2 text-sm text-gray-500 mb-2">
                <a href="{{ route('admin.settings.index') }}" class="hover:text-gray-700">Settings</a>
                <span>/</span>
                <span class="text-gray-900">Salesforce</span>
            </div>
            <h1 class="text-xl font-semibold text-gray-900">Salesforce Integration</h1>
            <p class="text-sm text-gray-500">Connect Salesforce to enrich calls with Chance data</p>
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

        <!-- Per-Account Connection -->
        <div class="space-y-6 mb-8">
            @foreach($accounts as $account)
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900">{{ $account['name'] }}</h3>
                            <p class="text-sm text-gray-500">
                                @if($account['sf_connected'])
                                    <span class="text-green-600">● Connected</span>
                                    @if($account['sf_connected_at'])
                                        <span class="ml-2">since {{ \Carbon\Carbon::parse($account['sf_connected_at'])->format('M j, Y') }}</span>
                                    @endif
                                @else
                                    <span class="text-gray-400">● Not connected</span>
                                @endif
                            </p>
                        </div>
                        @if($account['sf_connected'])
                            <div class="flex gap-2">
                                <button onclick="testConnection({{ $account['id'] }})" class="text-sm text-blue-600 hover:text-blue-700">
                                    Test
                                </button>
                                <form method="POST" action="{{ route('admin.salesforce.disconnect', $account['id']) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="text-sm text-red-600 hover:text-red-700">
                                        Disconnect
                                    </button>
                                </form>
                            </div>
                        @endif
                    </div>

                    <!-- Credentials Form (if not connected) -->
                    @if(!$account['sf_connected'])
                        <form method="POST" action="{{ route('admin.salesforce.credentials', $account['id']) }}" class="space-y-4">
                            @csrf
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Instance URL</label>
                                <input
                                    type="url"
                                    name="sf_instance_url"
                                    value="{{ $account['sf_instance_url'] }}"
                                    placeholder="https://yourorg.my.salesforce.com"
                                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                >
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Client ID</label>
                                    <input
                                        type="text"
                                        name="sf_client_id"
                                        value="{{ $account['sf_client_id'] }}"
                                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    >
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Client Secret</label>
                                    <input
                                        type="password"
                                        name="sf_client_secret"
                                        placeholder="Enter client secret"
                                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                    >
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700">
                                    Save & Connect to Salesforce
                                </button>
                            </div>
                        </form>
                    @endif

                    <!-- Field Mapping (if connected) -->
                    @if($account['sf_connected'])
                        <div class="mt-6 pt-6 border-t border-gray-100">
                            <h4 class="font-medium text-gray-900 mb-4">Field Mapping</h4>
                            <form method="POST" action="{{ route('admin.salesforce.field-mapping', $account['id']) }}">
                                @csrf
                                @php
                                    $mapping = array_merge($defaultFieldMapping, $account['sf_field_mapping']);
                                @endphp
                                <div class="grid grid-cols-2 gap-4 text-sm">
                                    <div>
                                        <label class="block text-gray-600 mb-1">Chance Object</label>
                                        <input name="chance_object" value="{{ $mapping['chance_object'] }}" class="w-full border border-gray-200 rounded-lg px-3 py-2">
                                    </div>
                                    <div>
                                        <label class="block text-gray-600 mb-1">CTM Call ID Field</label>
                                        <input name="ctm_call_id_field" value="{{ $mapping['ctm_call_id_field'] }}" class="w-full border border-gray-200 rounded-lg px-3 py-2">
                                    </div>
                                    <div>
                                        <label class="block text-gray-600 mb-1">Project Field</label>
                                        <input name="project_field" value="{{ $mapping['project_field'] }}" class="w-full border border-gray-200 rounded-lg px-3 py-2">
                                    </div>
                                    <div>
                                        <label class="block text-gray-600 mb-1">Land Sale Field</label>
                                        <input name="land_sale_field" value="{{ $mapping['land_sale_field'] }}" class="w-full border border-gray-200 rounded-lg px-3 py-2">
                                    </div>
                                    <div>
                                        <label class="block text-gray-600 mb-1">Contact Status Field</label>
                                        <input name="contact_status_field" value="{{ $mapping['contact_status_field'] }}" class="w-full border border-gray-200 rounded-lg px-3 py-2">
                                    </div>
                                    <div>
                                        <label class="block text-gray-600 mb-1">Appointment Made Field</label>
                                        <input name="appointment_made_field" value="{{ $mapping['appointment_made_field'] }}" class="w-full border border-gray-200 rounded-lg px-3 py-2">
                                    </div>
                                    <div>
                                        <label class="block text-gray-600 mb-1">Toured Property Field</label>
                                        <input name="toured_property_field" value="{{ $mapping['toured_property_field'] }}" class="w-full border border-gray-200 rounded-lg px-3 py-2">
                                    </div>
                                    <div>
                                        <label class="block text-gray-600 mb-1">Opportunity Created Field</label>
                                        <input name="opportunity_created_field" value="{{ $mapping['opportunity_created_field'] }}" class="w-full border border-gray-200 rounded-lg px-3 py-2">
                                    </div>
                                </div>
                                <button type="submit" class="mt-4 bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700">
                                    Save Field Mapping
                                </button>
                            </form>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <!-- Rep Mapping -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900">Rep Mapping</h3>
                <div class="flex gap-2">
                    @foreach($accounts as $account)
                        @if($account['sf_connected'])
                            <form method="POST" action="{{ route('admin.salesforce.auto-match-reps', $account['id']) }}" class="inline">
                                @csrf
                                <button type="submit" class="text-sm text-blue-600 hover:text-blue-700">
                                    Auto-Match {{ $account['name'] }} by Email
                                </button>
                            </form>
                        @endif
                    @endforeach
                </div>
            </div>
            <form method="POST" action="{{ route('admin.salesforce.rep-mapping') }}">
                @csrf
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100">
                            <th class="text-left py-2 text-gray-500 font-medium">Rep</th>
                            <th class="text-left py-2 text-gray-500 font-medium">Office</th>
                            <th class="text-left py-2 text-gray-500 font-medium">Salesforce User ID</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($reps as $index => $rep)
                            <tr class="border-b border-gray-50">
                                <td class="py-3">{{ $rep->name }}</td>
                                <td class="py-3 text-gray-500">{{ $rep->account?->name }}</td>
                                <td class="py-3">
                                    <input type="hidden" name="mappings[{{ $index }}][rep_id]" value="{{ $rep->id }}">
                                    <input
                                        type="text"
                                        name="mappings[{{ $index }}][sf_user_id]"
                                        value="{{ $rep->sf_user_id }}"
                                        placeholder="e.g., 005..."
                                        class="border border-gray-200 rounded-lg px-2 py-1 text-sm w-full"
                                    >
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <button type="submit" class="mt-4 bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700">
                    Save Rep Mapping
                </button>
            </form>
        </div>

        <!-- Project Mapping -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Project Mapping</h3>
            <p class="text-sm text-gray-500 mb-4">Map your projects to the Salesforce Project__c field value</p>
            <form method="POST" action="{{ route('admin.salesforce.project-mapping') }}">
                @csrf
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100">
                            <th class="text-left py-2 text-gray-500 font-medium">Project</th>
                            <th class="text-left py-2 text-gray-500 font-medium">Office</th>
                            <th class="text-left py-2 text-gray-500 font-medium">SF Project Name</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($projects as $index => $project)
                            <tr class="border-b border-gray-50">
                                <td class="py-3">{{ $project->name }}</td>
                                <td class="py-3 text-gray-500">{{ $project->account?->name }}</td>
                                <td class="py-3">
                                    <input type="hidden" name="mappings[{{ $index }}][project_id]" value="{{ $project->id }}">
                                    <input
                                        type="text"
                                        name="mappings[{{ $index }}][sf_project_name]"
                                        value="{{ $project->sf_project_name }}"
                                        placeholder="e.g., Hilltop Ranch"
                                        class="border border-gray-200 rounded-lg px-2 py-1 text-sm w-full"
                                    >
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <button type="submit" class="mt-4 bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700">
                    Save Project Mapping
                </button>
            </form>
        </div>
    </div>

    <script>
        async function testConnection(accountId) {
            try {
                const response = await fetch(`/admin/salesforce/${accountId}/test`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                const data = await response.json();
                alert(data.success ? 'Connection successful!' : 'Connection failed: ' + data.message);
            } catch (error) {
                alert('Error testing connection');
            }
        }
    </script>
</body>
</html>
