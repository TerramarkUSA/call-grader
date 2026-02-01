<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Call Grader</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body class="bg-[#f9fafb] min-h-screen">
    @include('admin.partials.nav')

    @php
        $activeTab = request('tab', 'general');
    @endphp

    <div class="max-w-4xl mx-auto px-8 py-6">
        <div class="mb-6">
            <h1 class="text-xl font-semibold text-gray-900">System Settings</h1>
            <p class="text-sm text-gray-500">Configure API credentials, integrations, and system options</p>
        </div>

        <!-- Tabs -->
        <div class="border-b border-gray-200 mb-6">
            <nav class="-mb-px flex space-x-8">
                <a
                    href="{{ route('admin.settings.index') }}"
                    class="py-2 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'general' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}"
                >
                    General
                </a>
                <a
                    href="{{ route('admin.settings.index', ['tab' => 'salesforce']) }}"
                    class="py-2 px-1 border-b-2 font-medium text-sm {{ $activeTab === 'salesforce' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}"
                >
                    Salesforce
                </a>
            </nav>
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

        @if($activeTab === 'general')

        <!-- API Settings -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">API Credentials</h3>
            <form method="POST" action="{{ route('admin.settings.update-api') }}">
                @csrf

                <div class="space-y-4">
                    <!-- Deepgram -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Deepgram API Key</label>
                        <div class="flex gap-2">
                            <input
                                type="password"
                                name="deepgram_api_key"
                                value="{{ $settings['deepgram_api_key'] }}"
                                placeholder="Enter Deepgram API key"
                                class="flex-1 px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            >
                            <button
                                type="button"
                                onclick="this.previousElementSibling.type = this.previousElementSibling.type === 'password' ? 'text' : 'password'"
                                class="px-4 py-2 border border-gray-200 rounded-lg font-medium text-gray-700 hover:bg-gray-50 transition-colors"
                            >
                                Show
                            </button>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Used for call transcription. Get your key at <a href="https://console.deepgram.com" target="_blank" class="text-blue-600 hover:text-blue-700">console.deepgram.com</a></p>
                    </div>

                </div>

                <div class="flex justify-between items-center mt-6">
                    <form method="POST" action="{{ route('admin.settings.test-deepgram') }}" class="inline">
                        @csrf
                        <button type="submit" class="px-4 py-2 border border-gray-200 rounded-lg font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                            Test Deepgram
                        </button>
                    </form>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 transition-colors">
                        Save API Settings
                    </button>
                </div>
            </form>
        </div>

        <!-- Email Configuration -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Email Configuration</h3>
            <p class="text-sm text-gray-600 mb-4">Email is configured via environment variables in Laravel Forge. Current configuration:</p>
            <div class="bg-gray-50 rounded-lg p-4 font-mono text-sm">
                <p><span class="text-gray-500">Mailer:</span> <span class="text-gray-900">{{ config('mail.default') }}</span></p>
                <p><span class="text-gray-500">From:</span> <span class="text-gray-900">{{ config('mail.from.address') ?: 'not set' }}</span></p>
                <p><span class="text-gray-500">Domain:</span> <span class="text-gray-900">{{ config('services.mailgun.domain') ?: 'not set' }}</span></p>
            </div>
            <div class="mt-4">
                <form method="POST" action="{{ route('admin.settings.test-email') }}" class="inline">
                    @csrf
                    <button type="submit" class="px-4 py-2 border border-gray-200 rounded-lg font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                        Send Test Email
                    </button>
                </form>
            </div>
        </div>

        <!-- Deepgram Transcription Settings -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Deepgram Transcription Options</h3>
            <form method="POST" action="{{ route('admin.settings.update-deepgram') }}">
                @csrf

                <div class="space-y-4">
                    <!-- Model Selection -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Transcription Model</label>
                        <select
                            name="deepgram_model"
                            class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        >
                            <option value="nova-3" {{ $settings['deepgram_model'] === 'nova-3' ? 'selected' : '' }}>Nova-3 (Recommended - Best accuracy)</option>
                            <option value="nova-2" {{ $settings['deepgram_model'] === 'nova-2' ? 'selected' : '' }}>Nova-2 (Previous generation)</option>
                            <option value="whisper-large" {{ $settings['deepgram_model'] === 'whisper-large' ? 'selected' : '' }}>Whisper Large (OpenAI model)</option>
                            <option value="whisper-medium" {{ $settings['deepgram_model'] === 'whisper-medium' ? 'selected' : '' }}>Whisper Medium (Faster, less accurate)</option>
                        </select>
                        <p class="text-xs text-gray-500 mt-1">Nova-3 offers the best accuracy for English speech-to-text</p>
                    </div>

                    <!-- Toggle Options -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Multichannel Audio</label>
                            <select
                                name="deepgram_multichannel"
                                id="deepgram_multichannel"
                                class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                onchange="handleMultichannelChange(this.value)"
                            >
                                <option value="true" {{ $settings['deepgram_multichannel'] === 'true' ? 'selected' : '' }}>Enabled</option>
                                <option value="false" {{ $settings['deepgram_multichannel'] === 'false' ? 'selected' : '' }}>Disabled</option>
                            </select>
                            <p class="text-xs text-gray-500 mt-1">Process separate audio channels (Rep/Prospect)</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Speaker Diarization</label>
                            <select
                                name="deepgram_diarize"
                                id="deepgram_diarize"
                                class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent disabled:bg-gray-100 disabled:text-gray-500"
                                {{ $settings['deepgram_multichannel'] === 'true' ? 'disabled' : '' }}
                            >
                                <option value="true" {{ $settings['deepgram_diarize'] === 'true' ? 'selected' : '' }}>Enabled</option>
                                <option value="false" {{ $settings['deepgram_diarize'] === 'false' || $settings['deepgram_multichannel'] === 'true' ? 'selected' : '' }}>Disabled</option>
                            </select>
                            <p class="text-xs text-gray-500 mt-1" id="diarize_help">
                                @if($settings['deepgram_multichannel'] === 'true')
                                    Disabled when multichannel is enabled
                                @else
                                    Identify different speakers in the call
                                @endif
                            </p>
                            <input type="hidden" name="deepgram_diarize" id="deepgram_diarize_hidden" value="{{ $settings['deepgram_multichannel'] === 'true' ? 'false' : $settings['deepgram_diarize'] }}" {{ $settings['deepgram_multichannel'] === 'true' ? '' : 'disabled' }}>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Smart Formatting</label>
                            <select
                                name="deepgram_smart_format"
                                class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            >
                                <option value="true" {{ $settings['deepgram_smart_format'] === 'true' ? 'selected' : '' }}>Enabled</option>
                                <option value="false" {{ $settings['deepgram_smart_format'] === 'false' ? 'selected' : '' }}>Disabled</option>
                            </select>
                            <p class="text-xs text-gray-500 mt-1">Format dates, times, numbers, etc.</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Punctuation</label>
                            <select
                                name="deepgram_punctuate"
                                class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            >
                                <option value="true" {{ $settings['deepgram_punctuate'] === 'true' ? 'selected' : '' }}>Enabled</option>
                                <option value="false" {{ $settings['deepgram_punctuate'] === 'false' ? 'selected' : '' }}>Disabled</option>
                            </select>
                            <p class="text-xs text-gray-500 mt-1">Add punctuation to transcript</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Utterances</label>
                            <select
                                name="deepgram_utterances"
                                class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            >
                                <option value="true" {{ $settings['deepgram_utterances'] === 'true' ? 'selected' : '' }}>Enabled</option>
                                <option value="false" {{ $settings['deepgram_utterances'] === 'false' ? 'selected' : '' }}>Disabled</option>
                            </select>
                            <p class="text-xs text-gray-500 mt-1">Split transcript into utterances</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Paragraphs</label>
                            <select
                                name="deepgram_paragraphs"
                                class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            >
                                <option value="true" {{ $settings['deepgram_paragraphs'] === 'true' ? 'selected' : '' }}>Enabled</option>
                                <option value="false" {{ $settings['deepgram_paragraphs'] === 'false' ? 'selected' : '' }}>Disabled</option>
                            </select>
                            <p class="text-xs text-gray-500 mt-1">Group transcript into paragraphs</p>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end mt-6">
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 transition-colors">
                        Save Transcription Settings
                    </button>
                </div>
            </form>
        </div>

        <!-- Grading Settings -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Grading Quality Thresholds</h3>
            <form method="POST" action="{{ route('admin.settings.update-grading') }}">
                @csrf

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Flag Threshold (%)</label>
                        <input
                            type="number"
                            name="grading_quality_flag_threshold"
                            value="{{ $settings['grading_quality_flag_threshold'] }}"
                            min="0"
                            max="100"
                            class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        >
                        <p class="text-xs text-gray-500 mt-1">Grades below this threshold will be flagged for review</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Suspicious Threshold (%)</label>
                        <input
                            type="number"
                            name="grading_quality_suspicious_threshold"
                            value="{{ $settings['grading_quality_suspicious_threshold'] }}"
                            min="0"
                            max="100"
                            class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        >
                        <p class="text-xs text-gray-500 mt-1">Grades below this threshold will be marked as suspicious</p>
                    </div>
                </div>

                <div class="flex justify-end mt-6">
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 transition-colors">
                        Save Grading Settings
                    </button>
                </div>
            </form>
        </div>

        <!-- Cost Alert Settings -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Cost Alert Thresholds</h3>
            <form method="POST" action="{{ route('admin.settings.update-alerts') }}">
                @csrf

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Daily Threshold ($)</label>
                        <input
                            type="number"
                            name="cost_alert_daily_threshold"
                            value="{{ $settings['cost_alert_daily_threshold'] }}"
                            min="0"
                            step="0.01"
                            class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        >
                        <p class="text-xs text-gray-500 mt-1">Alert when daily transcription costs exceed this amount</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Weekly Threshold ($)</label>
                        <input
                            type="number"
                            name="cost_alert_weekly_threshold"
                            value="{{ $settings['cost_alert_weekly_threshold'] }}"
                            min="0"
                            step="0.01"
                            class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        >
                        <p class="text-xs text-gray-500 mt-1">Alert when weekly transcription costs exceed this amount</p>
                    </div>
                </div>

                <div class="flex justify-end mt-6">
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 transition-colors">
                        Save Alert Settings
                    </button>
                </div>
            </form>
        </div>

        @else
        <!-- Salesforce Tab Content -->
        <!-- Connection Section -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Salesforce Connection</h3>
                    <p class="text-sm text-gray-500">
                        @if($sfConnected ?? false)
                            <span class="text-green-600">● Connected</span>
                            @if($sfConnectedAt ?? false)
                                <span class="ml-2">since {{ \Carbon\Carbon::parse($sfConnectedAt)->format('M j, Y') }}</span>
                            @endif
                        @else
                            <span class="text-gray-400">● Not connected</span>
                        @endif
                    </p>
                </div>
                @if($sfConnected ?? false)
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

            @if(!($sfConnected ?? false))
                <form method="POST" action="{{ route('admin.salesforce.credentials') }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Instance URL</label>
                        <input
                            type="url"
                            name="sf_instance_url"
                            value="{{ $sfInstanceUrl ?? '' }}"
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
                                value="{{ $sfClientId ?? '' }}"
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

        @if($sfConnected ?? false)
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
                            @foreach($objects ?? [] as $obj)
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
                                    @foreach($fields ?? [] as $field)
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
                                    @foreach($fields ?? [] as $field)
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
                                    @foreach($fields ?? [] as $field)
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
                                    @foreach($fields ?? [] as $field)
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
                                    @foreach($fields ?? [] as $field)
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
                                    @foreach($fields ?? [] as $field)
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
                                    @foreach($fields ?? [] as $field)
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
                                    @foreach($fields ?? [] as $field)
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

                @if($sfLastSyncAt ?? false)
                    <p class="mt-4 text-sm text-gray-500">
                        Last sync: {{ \Carbon\Carbon::parse($sfLastSyncAt)->format('M j, Y g:i A') }}
                    </p>
                @endif
            </div>
        @endif
        @endif
    </div>

    <script>
        function handleMultichannelChange(value) {
            const diarizeSelect = document.getElementById('deepgram_diarize');
            const diarizeHidden = document.getElementById('deepgram_diarize_hidden');
            const diarizeHelp = document.getElementById('diarize_help');

            if (value === 'true') {
                diarizeSelect.disabled = true;
                diarizeSelect.value = 'false';
                diarizeHidden.disabled = false;
                diarizeHidden.value = 'false';
                diarizeHelp.textContent = 'Disabled when multichannel is enabled';
            } else {
                diarizeSelect.disabled = false;
                diarizeHidden.disabled = true;
                diarizeHelp.textContent = 'Identify different speakers in the call';
            }
        }

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
