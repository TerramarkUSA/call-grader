<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Process Call - Call Grader</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <meta name="csrf-token" content="{{ csrf_token() }}">
</head>
<body class="bg-gray-100 min-h-screen">
    @include('manager.partials.nav')

    <div class="max-w-2xl mx-auto px-4 py-8">
        <!-- Back link -->
        <div class="mb-4">
            <a href="{{ route('manager.calls.index') }}" class="text-gray-600 hover:text-gray-900 text-sm">
                &larr; Back to Queue
            </a>
        </div>
        <!-- Call Info -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-xl font-bold mb-4">Process Call</h2>

            <div class="grid grid-cols-2 gap-4 text-sm mb-6">
                <div>
                    <span class="text-gray-500">Caller:</span>
                    <span class="font-medium">{{ $call->caller_name ?? 'Unknown' }}</span>
                </div>
                <div>
                    <span class="text-gray-500">Number:</span>
                    <span class="font-medium">{{ $call->caller_number }}</span>
                </div>
                <div>
                    <span class="text-gray-500">Duration:</span>
                    <span class="font-medium">{{ floor(($call->talk_time ?? 0) / 60) }}:{{ str_pad(($call->talk_time ?? 0) % 60, 2, '0', STR_PAD_LEFT) }}</span>
                </div>
                <div>
                    <span class="text-gray-500">Date:</span>
                    <span class="font-medium">{{ $call->called_at?->format('M j, Y g:i A') ?? '—' }}</span>
                </div>
                <div>
                    <span class="text-gray-500">Rep:</span>
                    <span class="font-medium">{{ $call->rep?->name ?? '—' }}</span>
                </div>
                <div>
                    <span class="text-gray-500">Project:</span>
                    <span class="font-medium">{{ $call->project?->name ?? '—' }}</span>
                </div>
            </div>

            <!-- Audio Preview -->
            <div class="mb-6">
                <div class="flex items-center justify-between mb-2">
                    <label class="block text-sm font-medium text-gray-700">Preview Audio</label>
                    <span class="text-xs text-green-600 font-medium">Free — no transcription cost</span>
                </div>
                <div id="audio-container">
                    <button
                        id="load-audio-btn"
                        type="button"
                        class="w-full flex items-center justify-center gap-2 px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg text-gray-600 hover:bg-gray-100 hover:text-gray-900 transition-colors"
                    >
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M8 5v14l11-7z"/>
                        </svg>
                        Click to Load Audio
                    </button>
                    <audio id="audio-player" controls class="hidden w-full mt-2">
                        Your browser does not support audio.
                    </audio>
                    <p id="audio-error" class="hidden text-sm text-red-600 mt-2 text-center"></p>
                </div>
            </div>

            @if($call->talk_time < 30)
                <!-- Short Call Warning -->
                <div class="bg-orange-50 border border-orange-200 rounded p-4 mb-6">
                    <div class="flex items-start gap-3">
                        <span class="text-2xl">⚠️</span>
                        <div>
                            <h3 class="font-bold text-orange-800">Short Call Warning</h3>
                            <p class="text-orange-700 text-sm">
                                This call is only {{ $call->talk_time }} seconds. Transcription costs money.
                                Are you sure this is a real call worth grading?
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Transcription Status -->
            <div id="status-idle" class="text-center py-8">
                <p class="text-gray-600 mb-4">Ready to transcribe this call</p>
                <button
                    onclick="startTranscription()"
                    class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium"
                >
                    Start Transcription
                </button>
                <p class="text-sm text-gray-500 mt-2">
                    Estimated cost: ${{ number_format($call->talk_time / 60 * 0.0043, 4) }}
                </p>
            </div>

            <div id="status-processing" class="text-center py-8" style="display: none;">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4"></div>
                <p class="text-gray-600">Fetching recording and transcribing...</p>
                <p class="text-sm text-gray-500">This may take a minute for longer calls</p>
            </div>

            <div id="status-error" class="text-center py-8" style="display: none;">
                <div class="text-red-500 text-4xl mb-4">✕</div>
                <p class="text-red-600 font-medium mb-2">Transcription Failed</p>
                <p class="text-gray-600 mb-4" id="error-message"></p>
                <div class="flex justify-center gap-3">
                    <button
                        onclick="startTranscription()"
                        class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
                    >
                        Retry
                    </button>
                    <a
                        href="{{ route('manager.calls.index') }}"
                        class="px-4 py-2 border border-gray-300 rounded hover:bg-gray-50"
                    >
                        Back to Queue
                    </a>
                </div>
            </div>

            <div id="status-success" class="text-center py-8" style="display: none;">
                <div class="text-green-500 text-4xl mb-4">✓</div>
                <p class="text-green-600 font-medium mb-4">Transcription Complete!</p>
                <p class="text-gray-600">Redirecting to grading...</p>
            </div>
        </div>

        <!-- Actions -->
        <div class="flex justify-between items-center">
            <a href="{{ route('manager.calls.index') }}" class="px-4 py-2 border border-gray-300 rounded-md hover:bg-gray-50">
                Cancel
            </a>
            <button type="button" id="skip-toggle-btn" class="px-4 py-2 text-orange-600 hover:text-orange-800 text-sm">
                Skip — Not Worth Grading
            </button>
        </div>

        <!-- Skip Reason Panel -->
        <div id="skip-panel" class="bg-white rounded-lg shadow p-6 mt-4" style="display: none;">
            <h3 class="text-sm font-semibold text-gray-900 mb-3">Why are you skipping this call?</h3>
            <div class="space-y-2 mb-4">
                @php
                    $skipReasons = [
                        'not_gradeable'   => 'Not Gradeable — cross-talk, can\'t follow',
                        'wrong_call_type' => 'Wrong Call Type — service, internal, not sales',
                        'poor_audio'      => 'Poor Audio Quality — can\'t hear clearly',
                        'not_a_real_call' => 'Not a Real Call — hang-up, wrong number, spam',
                        'too_short'       => 'Too Short to Grade — not enough substance',
                        'other'           => 'Other',
                    ];
                @endphp
                @foreach($skipReasons as $value => $label)
                    <label class="flex items-center gap-3 p-2 rounded-lg hover:bg-gray-50 cursor-pointer">
                        <input type="radio" name="skip_reason" value="{{ $value }}" class="text-orange-600 focus:ring-orange-500">
                        <span class="text-sm text-gray-700">{{ $label }}</span>
                    </label>
                @endforeach
            </div>
            <div class="flex gap-3">
                <button type="button" id="skip-confirm-btn" disabled
                    class="flex-1 px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 font-medium text-sm disabled:opacity-50 disabled:cursor-not-allowed">
                    Confirm Skip
                </button>
                <button type="button" id="skip-cancel-btn"
                    class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50 text-sm">
                    Cancel
                </button>
            </div>
        </div>
    </div>

    <script>
        // ========================================
        // Page Time Tracking
        // ========================================
        let _pageStart = Date.now();
        let _pageTotal = 0;
        let _pageVisible = true;
        const PAGE_SECONDS_CAP = 7200;

        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                _pageTotal += (Date.now() - _pageStart) / 1000;
                _pageVisible = false;
            } else {
                _pageStart = Date.now();
                _pageVisible = true;
            }
        });

        function getPageSeconds() {
            let total = _pageTotal;
            if (_pageVisible) total += (Date.now() - _pageStart) / 1000;
            return Math.min(Math.round(total), PAGE_SECONDS_CAP);
        }

        // Best-effort beacon on navigate away
        window.addEventListener('beforeunload', () => {
            const data = JSON.stringify({ page_seconds: getPageSeconds() });
            navigator.sendBeacon(
                '{{ route("manager.calls.page-time", $call) }}',
                new Blob([data], { type: 'application/json' })
            );
        });

        // ========================================
        // Skip Flow
        // ========================================
        const skipToggleBtn = document.getElementById('skip-toggle-btn');
        const skipPanel = document.getElementById('skip-panel');
        const skipCancelBtn = document.getElementById('skip-cancel-btn');
        const skipConfirmBtn = document.getElementById('skip-confirm-btn');
        const skipRadios = document.querySelectorAll('input[name="skip_reason"]');

        skipToggleBtn.addEventListener('click', () => {
            skipPanel.style.display = skipPanel.style.display === 'none' ? 'block' : 'none';
        });

        skipCancelBtn.addEventListener('click', () => {
            skipPanel.style.display = 'none';
        });

        skipRadios.forEach(r => r.addEventListener('change', () => {
            skipConfirmBtn.disabled = false;
        }));

        skipConfirmBtn.addEventListener('click', async () => {
            const reason = document.querySelector('input[name="skip_reason"]:checked')?.value;
            if (!reason) return;

            skipConfirmBtn.disabled = true;
            skipConfirmBtn.textContent = 'Skipping...';

            try {
                const response = await fetch('{{ route("manager.calls.skip", $call) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        skip_reason: reason,
                        page_seconds: getPageSeconds(),
                    }),
                });
                const data = await response.json();
                if (data.success) {
                    window.location.href = data.redirect;
                } else {
                    alert(data.message || 'Failed to skip call.');
                    skipConfirmBtn.disabled = false;
                    skipConfirmBtn.textContent = 'Confirm Skip';
                }
            } catch (e) {
                alert('Network error. Please try again.');
                skipConfirmBtn.disabled = false;
                skipConfirmBtn.textContent = 'Confirm Skip';
            }
        });

        // ========================================
        // Audio preview
        document.getElementById('load-audio-btn').addEventListener('click', async function() {
            const btn = this;
            const errorEl = document.getElementById('audio-error');
            errorEl.classList.add('hidden');
            btn.disabled = true;
            btn.innerHTML = `
                <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                Loading audio...
            `;

            try {
                const response = await fetch('{{ route("manager.calls.recording-url", $call) }}', {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                });
                const data = await response.json();

                if (data.success && data.recording_url) {
                    const audio = document.getElementById('audio-player');
                    audio.src = data.recording_url;
                    audio.classList.remove('hidden');
                    btn.classList.add('hidden');
                    audio.play();
                } else {
                    errorEl.textContent = data.message || 'Failed to load audio';
                    errorEl.classList.remove('hidden');
                    btn.disabled = false;
                    btn.innerHTML = `
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M8 5v14l11-7z"/>
                        </svg>
                        Click to Retry
                    `;
                }
            } catch (error) {
                errorEl.textContent = 'Network error. Please try again.';
                errorEl.classList.remove('hidden');
                btn.disabled = false;
                btn.innerHTML = `
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M8 5v14l11-7z"/>
                    </svg>
                    Click to Retry
                `;
            }
        });

        function startTranscription() {
            document.getElementById('status-idle').style.display = 'none';
            document.getElementById('status-error').style.display = 'none';
            document.getElementById('status-processing').style.display = 'block';

            fetch('{{ route("manager.calls.transcribe", $call) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('status-processing').style.display = 'none';
                    document.getElementById('status-success').style.display = 'block';

                    // Redirect after a moment
                    setTimeout(() => {
                        window.location.href = data.redirect;
                    }, 1500);
                } else {
                    document.getElementById('status-processing').style.display = 'none';
                    document.getElementById('status-error').style.display = 'block';
                    document.getElementById('error-message').textContent = data.message;
                }
            })
            .catch(error => {
                document.getElementById('status-processing').style.display = 'none';
                document.getElementById('status-error').style.display = 'block';
                document.getElementById('error-message').textContent = 'Network error. Please try again.';
            });
        }
    </script>
</body>
</html>
