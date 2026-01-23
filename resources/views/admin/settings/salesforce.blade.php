<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salesforce - Call Grader</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body class="bg-[#f9fafb] min-h-screen">
    @include('admin.partials.nav')

    <div class="max-w-5xl mx-auto px-8 py-6">
        <div class="mb-6">
            <h1 class="text-xl font-semibold text-gray-900">Salesforce Integration</h1>
            <p class="text-sm text-gray-500">Connect and sync data from Salesforce</p>
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

        <!-- Connection Section -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Connection</h3>
                    <p class="text-sm text-gray-500">
                        @if($sfConnected)
                            <span class="text-green-600">● Connected</span>
                            @if($sfConnectedAt)
                                <span class="ml-2">since {{ \Carbon\Carbon::parse($sfConnectedAt)->format('M j, Y') }}</span>
                            @endif
                        @else
                            <span class="text-gray-400">● Not connected</span>
                        @endif
                    </p>
                </div>
                @if($sfConnected)
                    <div class="flex gap-2">
                        <button onclick="testConnection()" class="text-sm text-blue-600 hover:text-blue-700">
                            Test
                        </button>
                        <form method="POST" action="{{ route('admin.salesforce.disconnect') }}" class="inline">
                            @csrf
                            <button type="submit" class="text-sm text-red-600 hover:text-red-700">
                                Disconnect
                            </button>
                        </form>
                    </div>
                @endif
            </div>

            @if(!$sfConnected)
                <form method="POST" action="{{ route('admin.salesforce.credentials') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Instance URL</label>
                        <input
                            type="url"
                            name="sf_instance_url"
                            value="{{ $sfInstanceUrl }}"
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
                                value="{{ $sfClientId }}"
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
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700">
                        Save & Connect to Salesforce
                    </button>
                </form>
            @endif
        </div>

        @if($sfConnected)
            <!-- Field Mapping Section -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-1">Field Mapping</h3>
                <p class="text-sm text-gray-500 mb-4">Map Salesforce fields to Call Grader fields</p>

                <form method="POST" action="{{ route('admin.salesforce.field-mapping') }}" id="fieldMappingForm">
                    @csrf

                    <!-- Object Selection -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Salesforce Object</label>
                        <select
                            name="chance_object"
                            id="objectSelect"
                            onchange="loadFields(this.value)"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        >
                            <option value="">Select an object...</option>
                            @foreach($objects as $obj)
                                <option value="{{ $obj['name'] }}" {{ ($fieldMapping['chance_object'] ?? '') === $obj['name'] ? 'selected' : '' }}>
                                    {{ $obj['label'] }} ({{ $obj['name'] }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Field Mappings -->
                    <div class="space-y-4" id="fieldMappings">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm text-gray-600 mb-1">CTM Call ID Field <span class="text-red-500">*</span></label>
                                <select name="ctm_call_id_field" class="field-select w-full border border-gray-200 rounded-lg px-3 py-2 text-sm" required>
                                    <option value="">Select field...</option>
                                    @foreach($fields as $field)
                                        <option value="{{ $field['name'] }}" {{ ($fieldMapping['ctm_call_id_field'] ?? '') === $field['name'] ? 'selected' : '' }}>
                                            {{ $field['label'] }} ({{ $field['name'] }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm text-gray-600 mb-1">Project Field</label>
                                <select name="project_field" class="field-select w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
                                    <option value="">Select field...</option>
                                    @foreach($fields as $field)
                                        <option value="{{ $field['name'] }}" {{ ($fieldMapping['project_field'] ?? '') === $field['name'] ? 'selected' : '' }}>
                                            {{ $field['label'] }} ({{ $field['name'] }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm text-gray-600 mb-1">Office Field</label>
                                <select name="office_field" class="field-select w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
                                    <option value="">Select field...</option>
                                    @foreach($fields as $field)
                                        <option value="{{ $field['name'] }}" {{ ($fieldMapping['office_field'] ?? '') === $field['name'] ? 'selected' : '' }}>
                                            {{ $field['label'] }} ({{ $field['name'] }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm text-gray-600 mb-1">Land Sale Field</label>
                                <select name="land_sale_field" class="field-select w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
                                    <option value="">Select field...</option>
                                    @foreach($fields as $field)
                                        <option value="{{ $field['name'] }}" {{ ($fieldMapping['land_sale_field'] ?? '') === $field['name'] ? 'selected' : '' }}>
                                            {{ $field['label'] }} ({{ $field['name'] }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm text-gray-600 mb-1">Contact Status Field</label>
                                <select name="contact_status_field" class="field-select w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
                                    <option value="">Select field...</option>
                                    @foreach($fields as $field)
                                        <option value="{{ $field['name'] }}" {{ ($fieldMapping['contact_status_field'] ?? '') === $field['name'] ? 'selected' : '' }}>
                                            {{ $field['label'] }} ({{ $field['name'] }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm text-gray-600 mb-1">Appointment Made Field</label>
                                <select name="appointment_made_field" class="field-select w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
                                    <option value="">Select field...</option>
                                    @foreach($fields as $field)
                                        <option value="{{ $field['name'] }}" {{ ($fieldMapping['appointment_made_field'] ?? '') === $field['name'] ? 'selected' : '' }}>
                                            {{ $field['label'] }} ({{ $field['name'] }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm text-gray-600 mb-1">Toured Property Field</label>
                                <select name="toured_property_field" class="field-select w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
                                    <option value="">Select field...</option>
                                    @foreach($fields as $field)
                                        <option value="{{ $field['name'] }}" {{ ($fieldMapping['toured_property_field'] ?? '') === $field['name'] ? 'selected' : '' }}>
                                            {{ $field['label'] }} ({{ $field['name'] }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm text-gray-600 mb-1">Opportunity Created Field</label>
                                <select name="opportunity_created_field" class="field-select w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
                                    <option value="">Select field...</option>
                                    @foreach($fields as $field)
                                        <option value="{{ $field['name'] }}" {{ ($fieldMapping['opportunity_created_field'] ?? '') === $field['name'] ? 'selected' : '' }}>
                                            {{ $field['label'] }} ({{ $field['name'] }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="mt-6 bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700">
                        Save Field Mapping
                    </button>
                </form>
            </div>

            <!-- Sync Section -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-1">Sync from Salesforce</h3>
                <p class="text-sm text-gray-500 mb-4">Pull Chance records with CTM Call IDs and match to calls</p>

                <form method="POST" action="{{ route('admin.salesforce.sync') }}" class="flex items-end gap-4">
                    @csrf
                    <div>
                        <label class="block text-sm text-gray-600 mb-1">Hours to sync</label>
                        <input
                            type="number"
                            name="hours"
                            value="24"
                            min="1"
                            max="720"
                            class="w-32 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        >
                    </div>
                    <button type="submit" class="bg-purple-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-purple-700">
                        Sync Now
                    </button>
                </form>

                @if($sfLastSyncAt)
                    <p class="mt-4 text-sm text-gray-500">
                        Last sync: {{ \Carbon\Carbon::parse($sfLastSyncAt)->format('M j, Y g:i A') }}
                    </p>
                @endif
            </div>
        @endif
    </div>

    <script>
        async function testConnection() {
            try {
                const response = await fetch('/admin/salesforce/test', {
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

        async function loadFields(objectName) {
            if (!objectName) {
                return;
            }

            // Show loading state
            const selects = document.querySelectorAll('.field-select');
            selects.forEach(select => {
                select.innerHTML = '<option value="">Loading...</option>';
                select.disabled = true;
            });

            try {
                const response = await fetch(`/admin/salesforce/fields?object=${encodeURIComponent(objectName)}`, {
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                const fields = await response.json();

                // Build options HTML
                let optionsHtml = '<option value="">Select field...</option>';
                fields.forEach(field => {
                    optionsHtml += `<option value="${field.name}">${field.label} (${field.name})</option>`;
                });

                // Update all field selects
                selects.forEach(select => {
                    const currentValue = select.dataset.currentValue || '';
                    select.innerHTML = optionsHtml;
                    select.disabled = false;
                    
                    // Try to restore previous value
                    if (currentValue) {
                        select.value = currentValue;
                    }
                });
            } catch (error) {
                console.error('Error loading fields:', error);
                selects.forEach(select => {
                    select.innerHTML = '<option value="">Error loading fields</option>';
                    select.disabled = false;
                });
            }
        }

        // Store current values on page load
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.field-select').forEach(select => {
                select.dataset.currentValue = select.value;
            });
        });
    </script>
</body>
</html>
