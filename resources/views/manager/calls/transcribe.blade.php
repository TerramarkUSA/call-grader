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
                    <span class="font-medium">{{ floor($call->talk_time / 60) }}:{{ str_pad($call->talk_time % 60, 2, '0', STR_PAD_LEFT) }}</span>
                </div>
                <div>
                    <span class="text-gray-500">Date:</span>
                    <span class="font-medium">{{ $call->called_at->format('M j, Y g:i A') }}</span>
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
        <div class="flex justify-between">
            <a href="{{ route('manager.calls.index') }}" class="px-4 py-2 border border-gray-300 rounded-md hover:bg-gray-50">
                Cancel
            </a>
            <form method="POST" action="{{ route('manager.calls.mark-bad', $call) }}" class="inline">
                @csrf
                <input type="hidden" name="call_quality" value="no_conversation">
                <input type="hidden" name="delete_recording" value="0">
                <button type="submit" class="px-4 py-2 text-orange-600 hover:text-orange-800">
                    Mark as Bad Call
                </button>
            </form>
        </div>
    </div>

    <script>
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
