<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Call Grader</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    @include('admin.partials.nav')

    <div class="max-w-4xl mx-auto px-4 py-6">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">System Settings</h1>
            <p class="text-gray-600">Configure API credentials and system options</p>
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

        <!-- API Settings -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h3 class="text-lg font-bold mb-4">API Credentials</h3>
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
                                class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            >
                            <button
                                type="button"
                                onclick="this.previousElementSibling.type = this.previousElementSibling.type === 'password' ? 'text' : 'password'"
                                class="px-3 py-2 border border-gray-300 rounded-md hover:bg-gray-50"
                            >
                                Show
                            </button>
                        </div>
                        <p class="text-sm text-gray-500 mt-1">Used for call transcription. Get your key at <a href="https://console.deepgram.com" target="_blank" class="text-blue-600 hover:underline">console.deepgram.com</a></p>
                    </div>

                    <hr class="my-4">

                    <!-- SendGrid -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">SendGrid API Key</label>
                        <div class="flex gap-2">
                            <input
                                type="password"
                                name="sendgrid_api_key"
                                value="{{ $settings['sendgrid_api_key'] }}"
                                placeholder="Enter SendGrid API key"
                                class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            >
                            <button
                                type="button"
                                onclick="this.previousElementSibling.type = this.previousElementSibling.type === 'password' ? 'text' : 'password'"
                                class="px-3 py-2 border border-gray-300 rounded-md hover:bg-gray-50"
                            >
                                Show
                            </button>
                        </div>
                        <p class="text-sm text-gray-500 mt-1">Used for sending emails. Get your key at <a href="https://app.sendgrid.com/settings/api_keys" target="_blank" class="text-blue-600 hover:underline">SendGrid API Keys</a></p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">SendGrid From Email</label>
                        <input
                            type="email"
                            name="sendgrid_from_email"
                            value="{{ $settings['sendgrid_from_email'] }}"
                            placeholder="noreply@yourdomain.com"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                        <p class="text-sm text-gray-500 mt-1">The email address that emails will be sent from (must be verified in SendGrid)</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">SendGrid From Name</label>
                        <input
                            type="text"
                            name="sendgrid_from_name"
                            value="{{ $settings['sendgrid_from_name'] }}"
                            placeholder="Call Grader"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                    </div>
                </div>

                <div class="flex justify-between items-center mt-6">
                    <div class="flex gap-2">
                        <form method="POST" action="{{ route('admin.settings.test-deepgram') }}" class="inline">
                            @csrf
                            <button type="submit" class="px-4 py-2 border border-gray-300 rounded-md hover:bg-gray-50">
                                Test Deepgram
                            </button>
                        </form>
                        <form method="POST" action="{{ route('admin.settings.test-email') }}" class="inline">
                            @csrf
                            <button type="submit" class="px-4 py-2 border border-gray-300 rounded-md hover:bg-gray-50">
                                Test Email
                            </button>
                        </form>
                    </div>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        Save API Settings
                    </button>
                </div>
            </form>

            <!-- Mail Config Debug Info -->
            <div class="mt-4 pt-4 border-t border-gray-200">
                <p class="text-sm text-gray-500">
                    Current mailer: <span class="font-mono">{{ config('mail.default') }}</span> |
                    From: <span class="font-mono">{{ config('mail.from.address') ?: 'not set' }}</span>
                </p>
            </div>
        </div>

        <!-- Deepgram Transcription Settings -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h3 class="text-lg font-bold mb-4">Deepgram Transcription Options</h3>
            <form method="POST" action="{{ route('admin.settings.update-deepgram') }}">
                @csrf

                <div class="space-y-4">
                    <!-- Model Selection -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Transcription Model</label>
                        <select
                            name="deepgram_model"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="nova-3" {{ $settings['deepgram_model'] === 'nova-3' ? 'selected' : '' }}>Nova-3 (Recommended - Best accuracy)</option>
                            <option value="nova-2" {{ $settings['deepgram_model'] === 'nova-2' ? 'selected' : '' }}>Nova-2 (Previous generation)</option>
                            <option value="whisper-large" {{ $settings['deepgram_model'] === 'whisper-large' ? 'selected' : '' }}>Whisper Large (OpenAI model)</option>
                            <option value="whisper-medium" {{ $settings['deepgram_model'] === 'whisper-medium' ? 'selected' : '' }}>Whisper Medium (Faster, less accurate)</option>
                        </select>
                        <p class="text-sm text-gray-500 mt-1">Nova-3 offers the best accuracy for English speech-to-text</p>
                    </div>

                    <!-- Toggle Options -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Multichannel Audio</label>
                            <select
                                name="deepgram_multichannel"
                                id="deepgram_multichannel"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                onchange="handleMultichannelChange(this.value)"
                            >
                                <option value="true" {{ $settings['deepgram_multichannel'] === 'true' ? 'selected' : '' }}>Enabled</option>
                                <option value="false" {{ $settings['deepgram_multichannel'] === 'false' ? 'selected' : '' }}>Disabled</option>
                            </select>
                            <p class="text-sm text-gray-500 mt-1">Process separate audio channels (Rep/Prospect)</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Speaker Diarization</label>
                            <select
                                name="deepgram_diarize"
                                id="deepgram_diarize"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:bg-gray-100 disabled:text-gray-500"
                                {{ $settings['deepgram_multichannel'] === 'true' ? 'disabled' : '' }}
                            >
                                <option value="true" {{ $settings['deepgram_diarize'] === 'true' ? 'selected' : '' }}>Enabled</option>
                                <option value="false" {{ $settings['deepgram_diarize'] === 'false' || $settings['deepgram_multichannel'] === 'true' ? 'selected' : '' }}>Disabled</option>
                            </select>
                            <p class="text-sm text-gray-500 mt-1" id="diarize_help">
                                @if($settings['deepgram_multichannel'] === 'true')
                                    Disabled when multichannel is enabled
                                @else
                                    Identify different speakers in the call
                                @endif
                            </p>
                            <!-- Hidden input to submit value when select is disabled -->
                            <input type="hidden" name="deepgram_diarize" id="deepgram_diarize_hidden" value="{{ $settings['deepgram_multichannel'] === 'true' ? 'false' : $settings['deepgram_diarize'] }}" {{ $settings['deepgram_multichannel'] === 'true' ? '' : 'disabled' }}>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Smart Formatting</label>
                            <select
                                name="deepgram_smart_format"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            >
                                <option value="true" {{ $settings['deepgram_smart_format'] === 'true' ? 'selected' : '' }}>Enabled</option>
                                <option value="false" {{ $settings['deepgram_smart_format'] === 'false' ? 'selected' : '' }}>Disabled</option>
                            </select>
                            <p class="text-sm text-gray-500 mt-1">Format dates, times, numbers, etc.</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Punctuation</label>
                            <select
                                name="deepgram_punctuate"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            >
                                <option value="true" {{ $settings['deepgram_punctuate'] === 'true' ? 'selected' : '' }}>Enabled</option>
                                <option value="false" {{ $settings['deepgram_punctuate'] === 'false' ? 'selected' : '' }}>Disabled</option>
                            </select>
                            <p class="text-sm text-gray-500 mt-1">Add punctuation to transcript</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Utterances</label>
                            <select
                                name="deepgram_utterances"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            >
                                <option value="true" {{ $settings['deepgram_utterances'] === 'true' ? 'selected' : '' }}>Enabled</option>
                                <option value="false" {{ $settings['deepgram_utterances'] === 'false' ? 'selected' : '' }}>Disabled</option>
                            </select>
                            <p class="text-sm text-gray-500 mt-1">Split transcript into utterances</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Paragraphs</label>
                            <select
                                name="deepgram_paragraphs"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                            >
                                <option value="true" {{ $settings['deepgram_paragraphs'] === 'true' ? 'selected' : '' }}>Enabled</option>
                                <option value="false" {{ $settings['deepgram_paragraphs'] === 'false' ? 'selected' : '' }}>Disabled</option>
                            </select>
                            <p class="text-sm text-gray-500 mt-1">Group transcript into paragraphs</p>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end mt-6">
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        Save Transcription Settings
                    </button>
                </div>
            </form>
        </div>

        <!-- Grading Settings -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h3 class="text-lg font-bold mb-4">Grading Quality Thresholds</h3>
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
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                        <p class="text-sm text-gray-500 mt-1">Grades below this threshold will be flagged for review</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Suspicious Threshold (%)</label>
                        <input
                            type="number"
                            name="grading_quality_suspicious_threshold"
                            value="{{ $settings['grading_quality_suspicious_threshold'] }}"
                            min="0"
                            max="100"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                        <p class="text-sm text-gray-500 mt-1">Grades below this threshold will be marked as suspicious</p>
                    </div>
                </div>

                <div class="flex justify-end mt-6">
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        Save Grading Settings
                    </button>
                </div>
            </form>
        </div>

        <!-- Cost Alert Settings -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-bold mb-4">Cost Alert Thresholds</h3>
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
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                        <p class="text-sm text-gray-500 mt-1">Alert when daily transcription costs exceed this amount</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Weekly Threshold ($)</label>
                        <input
                            type="number"
                            name="cost_alert_weekly_threshold"
                            value="{{ $settings['cost_alert_weekly_threshold'] }}"
                            min="0"
                            step="0.01"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                        <p class="text-sm text-gray-500 mt-1">Alert when weekly transcription costs exceed this amount</p>
                    </div>
                </div>

                <div class="flex justify-end mt-6">
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                        Save Alert Settings
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function handleMultichannelChange(value) {
            const diarizeSelect = document.getElementById('deepgram_diarize');
            const diarizeHidden = document.getElementById('deepgram_diarize_hidden');
            const diarizeHelp = document.getElementById('diarize_help');

            if (value === 'true') {
                // Multichannel enabled - disable diarization
                diarizeSelect.disabled = true;
                diarizeSelect.value = 'false';
                diarizeHidden.disabled = false;
                diarizeHidden.value = 'false';
                diarizeHelp.textContent = 'Disabled when multichannel is enabled';
            } else {
                // Multichannel disabled - enable diarization selection
                diarizeSelect.disabled = false;
                diarizeHidden.disabled = true;
                diarizeHelp.textContent = 'Identify different speakers in the call';
            }
        }
    </script>
</body>
</html>
