<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Call Review - Call Grader</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        .utterance-active {
            background-color: rgb(239, 246, 255) !important;
            box-shadow: inset 0 0 0 2px rgb(59, 130, 246);
        }
        .utterance-has-note {
            border-left: 3px solid rgb(59, 130, 246);
        }
        .utterance-has-objection {
            border-left: 3px solid rgb(239, 68, 68);
        }
        .modal-backdrop {
            background-color: rgba(0, 0, 0, 0.5);
        }
        .progress-track {
            background: #e5e7eb;
        }
        .progress-fill {
            background: #3b82f6;
        }
        input[type="range"] {
            -webkit-appearance: none;
            background: transparent;
        }
        input[type="range"]::-webkit-slider-thumb {
            -webkit-appearance: none;
            height: 12px;
            width: 12px;
            border-radius: 50%;
            background: #3b82f6;
            cursor: pointer;
            margin-top: -4px;
        }
        input[type="range"]::-webkit-slider-runnable-track {
            width: 100%;
            height: 4px;
            background: #e5e7eb;
            border-radius: 2px;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Header -->
    <div class="bg-white border-b">
        <div class="max-w-7xl mx-auto px-4 py-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <a href="{{ route('manager.calls.index') }}" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </a>
                    <div>
                        <h1 class="text-lg font-semibold text-gray-900">Call Review: {{ $call->caller_number ?? $call->ctm_activity_id }}</h1>
                        <p class="text-sm text-gray-500">{{ $call->called_at->format('n/j/Y, g:i:s A') }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    @if($existingGrade && $existingGrade->status === 'submitted')
                        <span class="px-3 py-1 text-sm font-medium text-green-700 bg-green-100 rounded-full">
                            Graded
                        </span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Audio Player -->
    <div class="bg-white border-b">
        <div class="max-w-7xl mx-auto px-4 py-3">
            <audio
                id="audio-player"
                src="{{ route('manager.calls.audio', $call) }}"
                preload="metadata"
            ></audio>

            <div class="flex items-center gap-4">
                <!-- Skip Back -->
                <button id="skip-to-start" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M6 6h2v12H6zm3.5 6l8.5 6V6z"/>
                    </svg>
                </button>

                <!-- Play/Pause Button -->
                <button
                    id="play-pause-btn"
                    class="w-10 h-10 flex items-center justify-center bg-blue-500 hover:bg-blue-600 rounded-full text-white transition-colors"
                >
                    <svg id="play-icon" class="w-5 h-5 ml-0.5" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M8 5v14l11-7z"/>
                    </svg>
                    <svg id="pause-icon" class="w-5 h-5 hidden" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/>
                    </svg>
                </button>

                <!-- Skip Forward -->
                <button id="skip-to-end" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M6 18l8.5-6L6 6v12zM16 6v12h2V6h-2z"/>
                    </svg>
                </button>

                <!-- Current Time -->
                <span id="current-time" class="text-sm text-gray-600 font-mono min-w-[40px]">0:00</span>

                <!-- Progress Bar -->
                <div class="flex-1 relative">
                    <div
                        id="progress-container"
                        class="h-1 bg-gray-200 rounded-full cursor-pointer relative"
                    >
                        <div
                            id="progress-bar"
                            class="absolute h-full bg-blue-500 rounded-full"
                            style="width: 0%"
                        ></div>
                        <div
                            id="progress-handle"
                            class="absolute w-3 h-3 bg-blue-500 rounded-full -top-1 -ml-1.5 opacity-0 hover:opacity-100 transition-opacity"
                            style="left: 0%"
                        ></div>
                    </div>
                </div>

                <!-- Duration -->
                <span id="duration" class="text-sm text-gray-600 font-mono min-w-[40px]">0:00</span>

                <!-- Playback Speed -->
                <select
                    id="playback-speed"
                    class="text-sm text-gray-600 bg-white border border-gray-200 rounded-lg px-2 py-1 focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                    <option value="0.5">0.5x</option>
                    <option value="1" selected>1x</option>
                    <option value="1.25">1.25x</option>
                    <option value="1.5">1.5x</option>
                    <option value="2">2x</option>
                </select>

                <!-- Volume -->
                <button id="volume-btn" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-[1600px] mx-auto px-4 py-6">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left Column: Transcript -->
            <div class="max-h-[calc(100vh-200px)] flex flex-col">
                <div class="bg-white rounded-xl shadow-sm overflow-hidden flex-1 flex flex-col min-h-0">
                    <div class="px-5 py-4 border-b">
                        <div class="flex items-center justify-between">
                            <div>
                                <h2 class="text-lg font-semibold text-gray-900">Transcript</h2>
                                <p class="text-sm text-gray-500">{{ count($transcript) }} utterances - Click any line to add a coaching note</p>
                            </div>
                            @if($isMultichannel)
                                <button
                                    id="swap-speakers-btn"
                                    type="button"
                                    class="text-sm px-3 py-1.5 rounded-lg border border-gray-200 hover:bg-gray-50 text-gray-600 flex items-center gap-1.5 transition-colors"
                                    title="Swap Rep/Prospect labels if they're backwards"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/>
                                    </svg>
                                    Swap Speakers
                                </button>
                            @endif
                        </div>
                    </div>

                    <div id="transcript-container" class="flex-1 overflow-y-auto">
                        @forelse($transcript as $index => $utterance)
                            @php
                                $rawSpeaker = $utterance['speaker'] ?? 0;
                                $effectiveSpeaker = $speakersSwapped ? (1 - $rawSpeaker) : $rawSpeaker;
                                $isRep = $effectiveSpeaker === 0;
                            @endphp
                            <div
                                class="utterance p-3 mb-3 rounded-lg {{ $isRep ? 'bg-blue-50 border-l-4 border-blue-400' : 'bg-green-50 border-l-4 border-green-400' }} cursor-pointer hover:bg-gray-100 transition-colors"
                                data-index="{{ $index }}"
                                data-start="{{ $utterance['start'] ?? 0 }}"
                                data-end="{{ $utterance['end'] ?? 0 }}"
                                data-text="{{ $utterance['text'] ?? '' }}"
                                data-speaker="{{ $rawSpeaker }}"
                            >
                                <!-- Speaker label and timestamp -->
                                <div class="flex items-center gap-3 mb-2">
                                    <div class="flex items-center gap-1.5">
                                        <svg class="w-4 h-4 {{ $isRep ? 'text-blue-500' : 'text-green-500' }}" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                                        </svg>
                                        <span class="speaker-label text-sm font-medium {{ $isRep ? 'text-blue-600' : 'text-green-600' }}">
                                            {{ $isRep ? 'Rep' : 'Prospect' }}
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-1.5 text-gray-400">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        <span class="text-sm">{{ gmdate('i:s', (int)($utterance['start'] ?? 0)) }}</span>
                                    </div>
                                    <!-- Note indicator -->
                                    <span class="note-indicator hidden text-blue-500">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M18 13V5a2 2 0 00-2-2H4a2 2 0 00-2 2v8a2 2 0 002 2h3l3 3 3-3h3a2 2 0 002-2z"/>
                                        </svg>
                                    </span>
                                </div>

                                <!-- Text -->
                                <p class="text-sm text-gray-700 leading-relaxed">{{ $utterance['text'] ?? '' }}</p>
                            </div>
                        @empty
                            <div class="px-6 py-12 text-center text-gray-500">
                                <p>No transcript available</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- Middle Column: Call Details + Rubric Grading -->
            <div class="max-h-[calc(100vh-200px)] flex flex-col space-y-4 overflow-y-auto">
                <!-- Call Details Card -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="px-5 py-3 border-b">
                        <h2 class="text-lg font-semibold text-gray-900">Call Details</h2>
                    </div>
                    <div class="px-5 py-3">
                        <div class="grid grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Rep Name</label>
                                <div class="text-sm text-gray-900">
                                    {{ $call->rep?->name ?? 'Unknown' }}
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Project</label>
                                <div class="text-sm text-gray-900">
                                    {{ $call->project?->name ?? 'Unknown' }}
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Outcome</label>
                                <div class="text-sm text-gray-900">
                                    {{ ucfirst(str_replace('_', ' ', $call->call_quality ?? 'pending')) }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Rubric Grading -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden flex-1 flex flex-col">
                    <div class="border-b">
                        <div class="flex">
                            <button class="flex-1 px-5 py-2.5 text-sm font-medium text-gray-900 border-b-2 border-blue-500 bg-white">
                                Rubric Grading
                            </button>
                            <button class="flex-1 px-5 py-2.5 text-sm font-medium text-gray-500 hover:text-gray-700 bg-gray-50">
                                Notes ({{ count($existingGrade?->coachingNotes ?? []) }})
                            </button>
                        </div>
                    </div>

                    <!-- Sales Call Evaluation -->
                    <div class="px-5 py-4 flex-1">
                        <div class="mb-3">
                            <h3 class="text-base font-semibold text-gray-900">Sales Call Evaluation</h3>
                            <p class="text-sm text-gray-500">Score each category 1-4 based on call performance</p>
                        </div>

                        <!-- Progress -->
                        <div class="flex items-center gap-3 mb-4">
                            <div class="flex-1 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                <div id="category-progress-bar" class="h-full bg-blue-500 transition-all" style="width: 0%"></div>
                            </div>
                            <span class="text-sm text-gray-500">
                                <span id="completed-categories">0</span>/{{ count($categories) }} categories
                            </span>
                        </div>

                        <!-- Categories -->
                        <div class="space-y-4">
                            @foreach($categories as $category)
                                <div class="category-card" data-category-id="{{ $category->id }}" data-weight="{{ $category->weight }}">
                                    <div class="mb-2">
                                        <h4 class="font-medium text-gray-900 text-sm">{{ $category->name }}</h4>
                                    </div>

                                    <!-- Score Buttons -->
                                    <div class="flex gap-2">
                                        @for($score = 1; $score <= 4; $score++)
                                            <button
                                                type="button"
                                                class="score-btn flex-1 py-2 text-center rounded-lg border-2 border-gray-200 text-gray-700 font-medium hover:border-blue-300 hover:bg-blue-50 transition-all text-sm"
                                                data-score="{{ $score }}"
                                            >
                                                {{ $score }}
                                            </button>
                                        @endfor
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Checkpoints + Actions -->
            <div class="max-h-[calc(100vh-200px)] flex flex-col space-y-4 overflow-y-auto">
                <!-- Checkpoints Section -->
                @if(count($positiveCheckpoints) > 0 || count($negativeCheckpoints) > 0)
                    <div class="bg-white rounded-xl shadow-sm overflow-hidden flex-1 flex flex-col">
                        <div class="px-5 py-3 border-b">
                            <h3 class="text-lg font-semibold text-gray-900">Checkpoints</h3>
                        </div>
                        <div class="px-5 py-4 flex-1 overflow-y-auto">
                            @if(count($positiveCheckpoints) > 0)
                                <div class="mb-4">
                                    <h4 class="text-sm font-medium text-green-700 mb-2">Should Observe</h4>
                                    @foreach($positiveCheckpoints as $checkpoint)
                                        <div class="checkpoint-row flex items-center justify-between py-2" data-checkpoint-id="{{ $checkpoint->id }}" data-type="positive">
                                            <span class="text-sm text-gray-700 pr-2">{{ $checkpoint->name }}</span>
                                            <div class="flex gap-1 flex-shrink-0">
                                                <button type="button" class="checkpoint-btn px-2.5 py-1 text-xs rounded-lg border border-gray-200 bg-gray-100 text-gray-600" data-value="null">—</button>
                                                <button type="button" class="checkpoint-btn px-2.5 py-1 text-xs rounded-lg border border-gray-200 hover:bg-green-50 hover:border-green-300" data-value="1">Yes</button>
                                                <button type="button" class="checkpoint-btn px-2.5 py-1 text-xs rounded-lg border border-gray-200 hover:bg-red-50 hover:border-red-300" data-value="0">No</button>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            @if(count($negativeCheckpoints) > 0)
                                <div>
                                    <h4 class="text-sm font-medium text-red-700 mb-2">Should NOT Observe</h4>
                                    @foreach($negativeCheckpoints as $checkpoint)
                                        <div class="checkpoint-row flex items-center justify-between py-2" data-checkpoint-id="{{ $checkpoint->id }}" data-type="negative">
                                            <span class="text-sm text-gray-700 pr-2">{{ $checkpoint->name }}</span>
                                            <div class="flex gap-1 flex-shrink-0">
                                                <button type="button" class="checkpoint-btn px-2.5 py-1 text-xs rounded-lg border border-gray-200 bg-gray-100 text-gray-600" data-value="null">—</button>
                                                <button type="button" class="checkpoint-btn px-2.5 py-1 text-xs rounded-lg border border-gray-200 hover:bg-red-50 hover:border-red-300" data-value="1">Yes</button>
                                                <button type="button" class="checkpoint-btn px-2.5 py-1 text-xs rounded-lg border border-gray-200 hover:bg-green-50 hover:border-green-300" data-value="0">No</button>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                <!-- Appointment Quality -->
                @if(in_array($call->call_quality, ['booked', 'appointment_set']) || $call->dial_status === 'booked')
                    <div id="appointment-quality-section" class="bg-white rounded-xl shadow-sm overflow-hidden">
                        <div class="px-5 py-4 bg-green-50">
                            <h4 class="font-medium text-gray-900 mb-2">Appointment Quality</h4>
                            <p class="text-sm text-gray-600 mb-3">How solid was this appointment?</p>
                            <div class="flex gap-2">
                                <button type="button" class="appointment-btn flex-1 py-2 px-3 rounded-lg text-sm font-medium border border-gray-200 bg-white hover:bg-gray-50" data-value="solid">Solid</button>
                                <button type="button" class="appointment-btn flex-1 py-2 px-3 rounded-lg text-sm font-medium border border-gray-200 bg-white hover:bg-gray-50" data-value="tentative">Tentative</button>
                                <button type="button" class="appointment-btn flex-1 py-2 px-3 rounded-lg text-sm font-medium border border-gray-200 bg-white hover:bg-gray-50" data-value="backed_in">Backed-in</button>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Action Buttons -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden mt-auto">
                    <div class="px-5 py-4">
                        <div class="flex gap-3">
                            <button
                                type="button"
                                id="save-draft-btn"
                                class="flex-1 py-2.5 px-4 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 rounded-xl font-medium transition-colors"
                            >
                                Save Draft
                            </button>
                            <button
                                type="button"
                                id="submit-btn"
                                class="flex-1 py-2.5 px-4 bg-blue-500 hover:bg-blue-600 text-white rounded-xl font-medium transition-colors"
                            >
                                Submit Grade
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Note Modal -->
    <div id="add-note-modal" class="fixed inset-0 z-50 hidden">
        <div class="modal-backdrop absolute inset-0" onclick="closeAddNoteModal()"></div>
        <div class="absolute inset-0 flex items-center justify-center p-4 pointer-events-none">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-lg pointer-events-auto">
                <div class="px-6 py-4 border-b flex items-center justify-between">
                    <h3 class="font-semibold text-gray-900">Add Coaching Note</h3>
                    <button onclick="closeAddNoteModal()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="px-6 py-4 bg-gray-50 border-b">
                    <p class="text-xs text-gray-500 mb-1">Selected transcript:</p>
                    <p id="modal-transcript-text" class="text-sm text-gray-700 italic">"..."</p>
                    <p id="modal-timestamp" class="text-xs text-gray-400 mt-1">0:00</p>
                </div>

                <div class="px-6 py-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Coaching Note</label>
                        <textarea
                            id="note-text-input"
                            rows="3"
                            class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            placeholder="What should the rep have done differently?"
                        ></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Category Tag <span class="text-gray-400 font-normal">(optional)</span></label>
                        <select id="note-category-select" class="w-full border border-gray-200 rounded-xl px-4 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                            <option value="">No category</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="border rounded-xl p-4 bg-gray-50">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" id="is-objection-checkbox" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"/>
                            <span class="text-sm font-medium text-gray-700">This is an objection</span>
                        </label>

                        <div id="objection-details" class="mt-3 space-y-3 pl-6 hidden">
                            <div>
                                <label class="block text-sm text-gray-600 mb-1">Objection Type</label>
                                <select id="objection-type-select" class="w-full border rounded-lg px-3 py-2 text-sm">
                                    <option value="">Select type...</option>
                                    @foreach($objectionTypes as $type)
                                        <option value="{{ $type->id }}">{{ $type->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm text-gray-600 mb-1">Outcome</label>
                                <div class="flex gap-2">
                                    <button type="button" id="outcome-overcame-btn" onclick="setObjectionOutcome('overcame')" class="flex-1 py-2 px-3 rounded-lg text-sm font-medium bg-white border hover:bg-gray-50">Overcame</button>
                                    <button type="button" id="outcome-failed-btn" onclick="setObjectionOutcome('failed')" class="flex-1 py-2 px-3 rounded-lg text-sm font-medium bg-white border hover:bg-gray-50">Failed</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="px-6 py-4 border-t flex justify-end gap-3">
                    <button onclick="closeAddNoteModal()" class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-lg">Cancel</button>
                    <button id="save-note-btn" onclick="saveNote()" class="px-4 py-2 text-sm bg-blue-500 text-white rounded-lg hover:bg-blue-600">Save Note</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Why No Appointment Modal -->
    <div id="why-no-appointment-modal" class="fixed inset-0 z-50 hidden">
        <div class="modal-backdrop absolute inset-0"></div>
        <div class="absolute inset-0 flex items-center justify-center p-4 pointer-events-none">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-md pointer-events-auto">
                <div class="px-6 py-4 border-b bg-orange-50">
                    <h3 class="font-semibold text-gray-900">Why No Appointment?</h3>
                    <p class="text-sm text-gray-600 mt-1">Select the objection(s) that prevented booking.</p>
                </div>

                <div class="px-6 py-4 max-h-[300px] overflow-y-auto">
                    <div class="space-y-2" id="no-appt-objections-list">
                        @foreach($objectionTypes as $type)
                            <label class="flex items-center gap-3 p-2 rounded-lg hover:bg-gray-50 cursor-pointer">
                                <input type="checkbox" value="{{ $type->id }}" class="no-appt-objection-checkbox rounded border-gray-300 text-blue-600 focus:ring-blue-500"/>
                                <span class="text-sm text-gray-700">{{ $type->name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="px-6 pb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Additional notes <span class="text-gray-400 font-normal">(optional)</span></label>
                    <textarea id="no-appt-notes" rows="2" class="w-full border rounded-xl px-4 py-2 text-sm" placeholder="Any other context..."></textarea>
                </div>

                <div class="px-6 py-4 border-t flex justify-end gap-3">
                    <button onclick="skipNoAppointment()" class="px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 rounded-lg">Skip</button>
                    <button id="save-no-appt-btn" onclick="saveNoAppointmentReason()" class="px-4 py-2 text-sm bg-blue-500 text-white rounded-lg hover:bg-blue-600">Continue to Submit</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // ========================================
        // State
        // ========================================
        const state = {
            categoryScores: {},
            checkpointResponses: {},
            appointmentQuality: null,
            playbackSeconds: {{ $existingGrade?->playback_seconds ?? 0 }},
            isPlaying: false,
            playbackStartTime: null,
            categories: @json($categories),
            notes: [],
            selectedUtterance: null,
            objectionOutcome: null,
            callId: {{ $call->id }},
            gradeId: {{ $existingGrade?->id ?? 'null' }},
            speakersSwapped: {{ $speakersSwapped ? 'true' : 'false' }},
            isMultichannel: {{ $isMultichannel ? 'true' : 'false' }},
        };

        // Load existing grade data
        @if($existingGrade)
            @foreach($existingGrade->categoryScores as $cs)
                state.categoryScores[{{ $cs->rubric_category_id }}] = {{ $cs->score }};
            @endforeach
            @foreach($existingGrade->checkpointResponses as $cr)
                state.checkpointResponses[{{ $cr->rubric_checkpoint_id }}] = {{ $cr->observed ? 'true' : 'false' }};
            @endforeach
            @if($existingGrade->appointment_quality)
                state.appointmentQuality = "{{ $existingGrade->appointment_quality }}";
            @endif
        @endif

        // ========================================
        // Audio Player
        // ========================================
        const audio = document.getElementById('audio-player');
        const playPauseBtn = document.getElementById('play-pause-btn');
        const playIcon = document.getElementById('play-icon');
        const pauseIcon = document.getElementById('pause-icon');
        const progressContainer = document.getElementById('progress-container');
        const progressBar = document.getElementById('progress-bar');
        const progressHandle = document.getElementById('progress-handle');
        const currentTimeEl = document.getElementById('current-time');
        const durationEl = document.getElementById('duration');
        const playbackSpeedEl = document.getElementById('playback-speed');

        function formatTime(seconds) {
            if (isNaN(seconds)) return '0:00';
            const mins = Math.floor(seconds / 60);
            const secs = Math.floor(seconds % 60);
            return `${mins}:${secs.toString().padStart(2, '0')}`;
        }

        audio.addEventListener('loadedmetadata', () => {
            durationEl.textContent = formatTime(audio.duration);
        });

        audio.addEventListener('timeupdate', () => {
            currentTimeEl.textContent = formatTime(audio.currentTime);
            const percent = (audio.currentTime / audio.duration) * 100;
            progressBar.style.width = `${percent}%`;
            if (progressHandle) progressHandle.style.left = `${percent}%`;
            highlightCurrentUtterance(audio.currentTime);
        });

        audio.addEventListener('play', () => {
            state.isPlaying = true;
            state.playbackStartTime = Date.now();
            playIcon.classList.add('hidden');
            pauseIcon.classList.remove('hidden');
            startPlaybackTracking();
        });

        audio.addEventListener('pause', () => {
            state.isPlaying = false;
            stopPlaybackTracking();
            playIcon.classList.remove('hidden');
            pauseIcon.classList.add('hidden');
        });

        audio.addEventListener('ended', () => {
            state.isPlaying = false;
            stopPlaybackTracking();
            playIcon.classList.remove('hidden');
            pauseIcon.classList.add('hidden');
        });

        playPauseBtn.addEventListener('click', () => {
            if (audio.paused) {
                audio.play();
            } else {
                audio.pause();
            }
        });

        progressContainer.addEventListener('click', (e) => {
            const rect = progressContainer.getBoundingClientRect();
            const percent = (e.clientX - rect.left) / rect.width;
            audio.currentTime = percent * audio.duration;
        });

        playbackSpeedEl.addEventListener('change', () => {
            audio.playbackRate = parseFloat(playbackSpeedEl.value);
        });

        document.getElementById('skip-to-start').addEventListener('click', () => {
            audio.currentTime = Math.max(0, audio.currentTime - 10);
        });

        document.getElementById('skip-to-end').addEventListener('click', () => {
            audio.currentTime = Math.min(audio.duration, audio.currentTime + 10);
        });

        let trackingInterval = null;

        function startPlaybackTracking() {
            trackingInterval = setInterval(() => {
                if (state.isPlaying && state.playbackStartTime) {
                    const elapsed = (Date.now() - state.playbackStartTime) / 1000;
                    state.playbackSeconds = {{ $existingGrade?->playback_seconds ?? 0 }} + elapsed * parseFloat(playbackSpeedEl.value);
                }
            }, 1000);
        }

        function stopPlaybackTracking() {
            if (trackingInterval) {
                clearInterval(trackingInterval);
                trackingInterval = null;
            }
            if (state.playbackStartTime) {
                const elapsed = (Date.now() - state.playbackStartTime) / 1000;
                state.playbackSeconds = {{ $existingGrade?->playback_seconds ?? 0 }} + elapsed * parseFloat(playbackSpeedEl.value);
                state.playbackStartTime = null;
            }
        }

        // ========================================
        // Transcript Viewer
        // ========================================
        const utterances = document.querySelectorAll('.utterance');
        let currentUtteranceIndex = -1;

        utterances.forEach((utterance, index) => {
            utterance.addEventListener('click', (e) => {
                const start = parseFloat(utterance.dataset.start);
                const end = parseFloat(utterance.dataset.end);
                const text = utterance.dataset.text;
                const idx = parseInt(utterance.dataset.index);

                openAddNoteModal({
                    lineIndexStart: idx,
                    lineIndexEnd: idx,
                    timestampStart: start,
                    timestampEnd: end,
                    text: text,
                });
            });

            utterance.addEventListener('dblclick', (e) => {
                e.stopPropagation();
                const start = parseFloat(utterance.dataset.start);
                audio.currentTime = start;
                audio.play();
            });
        });

        function highlightCurrentUtterance(currentTime) {
            utterances.forEach((utterance, index) => {
                const start = parseFloat(utterance.dataset.start);
                const end = parseFloat(utterance.dataset.end);

                if (currentTime >= start && currentTime < end) {
                    if (currentUtteranceIndex !== index) {
                        utterances.forEach(u => u.classList.remove('utterance-active'));
                        utterance.classList.add('utterance-active');
                        utterance.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        currentUtteranceIndex = index;
                    }
                }
            });
        }

        // ========================================
        // Category Scoring
        // ========================================
        const categoryCards = document.querySelectorAll('.category-card');

        categoryCards.forEach(card => {
            const categoryId = card.dataset.categoryId;
            const scoreButtons = card.querySelectorAll('.score-btn');

            scoreButtons.forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const score = parseInt(btn.dataset.score);
                    state.categoryScores[categoryId] = score;
                    updateCategoryDisplay(card, score);
                    updateOverallScore();
                });
            });

            // Initialize from existing data
            if (state.categoryScores[categoryId]) {
                updateCategoryDisplay(card, state.categoryScores[categoryId]);
            }
        });

        function updateCategoryDisplay(card, score) {
            const scoreButtons = card.querySelectorAll('.score-btn');

            scoreButtons.forEach(btn => {
                const btnScore = parseInt(btn.dataset.score);
                btn.classList.remove('border-blue-500', 'bg-blue-500', 'text-white', 'border-gray-200', 'text-gray-700');

                if (btnScore === score) {
                    btn.classList.add('border-blue-500', 'bg-blue-500', 'text-white');
                } else {
                    btn.classList.add('border-gray-200', 'text-gray-700');
                }
            });
        }

        function updateOverallScore() {
            const totalCategories = state.categories.length;
            const scoredCategories = Object.keys(state.categoryScores).length;

            let totalWeightedScore = 0;
            let totalWeight = 0;

            state.categories.forEach(category => {
                const score = state.categoryScores[category.id];
                if (score !== undefined && score !== null) {
                    totalWeightedScore += score * parseFloat(category.weight);
                    totalWeight += parseFloat(category.weight);
                }
            });

            // Update progress
            document.getElementById('completed-categories').textContent = scoredCategories;
            const progressPercent = (scoredCategories / totalCategories) * 100;
            document.getElementById('category-progress-bar').style.width = progressPercent + '%';
        }

        // ========================================
        // Checkpoint Responses
        // ========================================
        const checkpointRows = document.querySelectorAll('.checkpoint-row');

        checkpointRows.forEach(row => {
            const checkpointId = row.dataset.checkpointId;
            const type = row.dataset.type;
            const buttons = row.querySelectorAll('.checkpoint-btn');

            buttons.forEach(btn => {
                btn.addEventListener('click', () => {
                    const value = btn.dataset.value;

                    if (value === 'null') {
                        delete state.checkpointResponses[checkpointId];
                    } else {
                        state.checkpointResponses[checkpointId] = value === '1';
                    }

                    updateCheckpointDisplay(row, value === 'null' ? null : value === '1', type);
                });
            });

            if (state.checkpointResponses[checkpointId] !== undefined) {
                updateCheckpointDisplay(row, state.checkpointResponses[checkpointId], type);
            }
        });

        function updateCheckpointDisplay(row, observed, type) {
            const buttons = row.querySelectorAll('.checkpoint-btn');

            buttons.forEach(btn => {
                const value = btn.dataset.value;
                btn.classList.remove('bg-green-500', 'bg-red-500', 'text-white', 'bg-gray-100');

                if (value === 'null') {
                    btn.classList.add(observed === null ? 'bg-gray-200' : 'bg-gray-100');
                } else if (value === '1') {
                    if (observed === true) {
                        btn.classList.add(type === 'positive' ? 'bg-green-500' : 'bg-red-500', 'text-white');
                    }
                } else {
                    if (observed === false) {
                        btn.classList.add(type === 'positive' ? 'bg-red-500' : 'bg-green-500', 'text-white');
                    }
                }
            });
        }

        // ========================================
        // Appointment Quality
        // ========================================
        const appointmentBtns = document.querySelectorAll('.appointment-btn');

        appointmentBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                state.appointmentQuality = btn.dataset.value;
                updateAppointmentDisplay();
            });
        });

        function updateAppointmentDisplay() {
            appointmentBtns.forEach(btn => {
                const value = btn.dataset.value;
                btn.classList.remove('bg-blue-500', 'text-white', 'border-blue-500');
                btn.classList.add('bg-white', 'border-gray-200');

                if (value === state.appointmentQuality) {
                    btn.classList.remove('bg-white', 'border-gray-200');
                    btn.classList.add('bg-blue-500', 'text-white', 'border-blue-500');
                }
            });
        }

        if (state.appointmentQuality) {
            updateAppointmentDisplay();
        }

        // ========================================
        // Save/Submit
        // ========================================
        const saveDraftBtn = document.getElementById('save-draft-btn');
        const submitBtn = document.getElementById('submit-btn');

        async function saveGrade(status) {
            saveDraftBtn.disabled = true;
            submitBtn.disabled = true;

            const originalSaveText = saveDraftBtn.textContent;
            const originalSubmitText = submitBtn.textContent;

            if (status === 'draft') {
                saveDraftBtn.textContent = 'Saving...';
            } else {
                submitBtn.textContent = 'Submitting...';
            }

            try {
                const response = await fetch('{{ route("manager.calls.grade.store", $call) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        category_scores: state.categoryScores,
                        checkpoint_responses: state.checkpointResponses,
                        appointment_quality: state.appointmentQuality,
                        playback_seconds: Math.floor(state.playbackSeconds),
                        status: status,
                    }),
                });

                const data = await response.json();

                if (data.success) {
                    if (data.redirect) {
                        window.location.href = data.redirect;
                    } else {
                        alert(data.message);
                    }
                } else {
                    alert(data.message || 'Failed to save grade');
                }
            } catch (error) {
                console.error('Error saving grade:', error);
                alert('Network error. Please try again.');
            } finally {
                saveDraftBtn.disabled = false;
                submitBtn.disabled = false;
                saveDraftBtn.textContent = originalSaveText;
                submitBtn.textContent = originalSubmitText;
            }
        }

        saveDraftBtn.addEventListener('click', () => saveGrade('draft'));

        submitBtn.addEventListener('click', () => {
            const totalCategories = state.categories.length;
            const scoredCategories = Object.keys(state.categoryScores).length;

            if (scoredCategories < totalCategories) {
                if (!confirm('Some categories are not scored. Submit anyway?')) {
                    return;
                }
            }

            const appointmentSection = document.getElementById('appointment-quality-section');
            if (!appointmentSection) {
                showWhyNoAppointmentModal();
                return;
            }

            saveGrade('submitted');
        });

        // ========================================
        // Initialize
        // ========================================
        updateOverallScore();
        loadNotes();

        // ========================================
        // Swap Speakers (Multichannel)
        // ========================================
        const swapSpeakersBtn = document.getElementById('swap-speakers-btn');
        if (swapSpeakersBtn) {
            swapSpeakersBtn.addEventListener('click', swapSpeakers);
        }

        async function swapSpeakers() {
            const btn = document.getElementById('swap-speakers-btn');
            btn.disabled = true;
            btn.innerHTML = '<span class="animate-pulse">Swapping...</span>';

            try {
                const response = await fetch(`/manager/calls/${state.callId}/swap-speakers`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                });

                const data = await response.json();

                if (data.success) {
                    state.speakersSwapped = data.speakers_swapped;
                    updateSpeakerLabels();
                }
            } catch (error) {
                console.error('Error swapping speakers:', error);
                alert('Failed to swap speakers.');
            } finally {
                btn.disabled = false;
                btn.innerHTML = `
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/>
                    </svg>
                    Swap Speakers
                `;
            }
        }

        function updateSpeakerLabels() {
            document.querySelectorAll('.utterance').forEach(utterance => {
                const rawSpeaker = parseInt(utterance.dataset.speaker);
                const effectiveSpeaker = state.speakersSwapped ? (1 - rawSpeaker) : rawSpeaker;
                const isRep = effectiveSpeaker === 0;

                const label = utterance.querySelector('.speaker-label');
                const icon = utterance.querySelector('svg');

                if (label) {
                    label.classList.remove('text-blue-600', 'text-green-600');
                    if (isRep) {
                        label.textContent = 'Rep';
                        label.classList.add('text-blue-600');
                    } else {
                        label.textContent = 'Prospect';
                        label.classList.add('text-green-600');
                    }
                }

                if (icon) {
                    icon.classList.remove('text-blue-500', 'text-green-500');
                    icon.classList.add(isRep ? 'text-blue-500' : 'text-green-500');
                }
            });
        }

        // ========================================
        // Coaching Notes
        // ========================================
        async function loadNotes() {
            try {
                const response = await fetch(`/manager/calls/${state.callId}/notes`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                });
                const notes = await response.json();
                state.notes = notes;
                updateTranscriptNoteIndicators();
            } catch (error) {
                console.error('Error loading notes:', error);
            }
        }

        function updateTranscriptNoteIndicators() {
            document.querySelectorAll('.utterance').forEach(u => {
                u.classList.remove('utterance-has-note', 'utterance-has-objection');
                const indicator = u.querySelector('.note-indicator');
                if (indicator) indicator.classList.add('hidden');
            });

            state.notes.forEach(note => {
                const utterance = document.querySelector(`.utterance[data-index="${note.line_index_start}"]`);
                if (utterance) {
                    if (note.is_objection) {
                        utterance.classList.add('utterance-has-objection');
                    } else {
                        utterance.classList.add('utterance-has-note');
                    }
                    const indicator = utterance.querySelector('.note-indicator');
                    if (indicator) indicator.classList.remove('hidden');
                }
            });
        }

        function openAddNoteModal(selection) {
            state.selectedUtterance = selection;
            state.objectionOutcome = null;

            document.getElementById('modal-transcript-text').textContent = `"${selection.text}"`;
            document.getElementById('modal-timestamp').textContent = formatTime(selection.timestampStart);

            document.getElementById('note-text-input').value = '';
            document.getElementById('note-category-select').value = '';
            document.getElementById('is-objection-checkbox').checked = false;
            document.getElementById('objection-details').classList.add('hidden');
            document.getElementById('objection-type-select').value = '';
            resetOutcomeButtons();

            document.getElementById('add-note-modal').classList.remove('hidden');
            document.getElementById('note-text-input').focus();
        }

        function closeAddNoteModal() {
            document.getElementById('add-note-modal').classList.add('hidden');
            state.selectedUtterance = null;
        }

        document.getElementById('is-objection-checkbox').addEventListener('change', function() {
            const details = document.getElementById('objection-details');
            if (this.checked) {
                details.classList.remove('hidden');
            } else {
                details.classList.add('hidden');
            }
        });

        function setObjectionOutcome(outcome) {
            state.objectionOutcome = outcome;
            const overcameBtn = document.getElementById('outcome-overcame-btn');
            const failedBtn = document.getElementById('outcome-failed-btn');

            resetOutcomeButtons();

            if (outcome === 'overcame') {
                overcameBtn.classList.remove('bg-white', 'border');
                overcameBtn.classList.add('bg-green-500', 'text-white');
            } else if (outcome === 'failed') {
                failedBtn.classList.remove('bg-white', 'border');
                failedBtn.classList.add('bg-red-500', 'text-white');
            }
        }

        function resetOutcomeButtons() {
            const overcameBtn = document.getElementById('outcome-overcame-btn');
            const failedBtn = document.getElementById('outcome-failed-btn');

            overcameBtn.className = 'flex-1 py-2 px-3 rounded-lg text-sm font-medium bg-white border hover:bg-gray-50';
            failedBtn.className = 'flex-1 py-2 px-3 rounded-lg text-sm font-medium bg-white border hover:bg-gray-50';
        }

        async function saveNote() {
            const noteText = document.getElementById('note-text-input').value.trim();
            const categoryId = document.getElementById('note-category-select').value || null;
            const isObjection = document.getElementById('is-objection-checkbox').checked;
            const objectionTypeId = document.getElementById('objection-type-select').value || null;

            if (!noteText) {
                alert('Please enter a note.');
                return;
            }

            if (isObjection && (!objectionTypeId || !state.objectionOutcome)) {
                alert('Please select an objection type and outcome.');
                return;
            }

            const saveBtn = document.getElementById('save-note-btn');
            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving...';

            try {
                const response = await fetch(`/manager/calls/${state.callId}/notes`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        grade_id: state.gradeId,
                        line_index_start: state.selectedUtterance.lineIndexStart,
                        line_index_end: state.selectedUtterance.lineIndexEnd,
                        timestamp_start: state.selectedUtterance.timestampStart,
                        timestamp_end: state.selectedUtterance.timestampEnd,
                        transcript_text: state.selectedUtterance.text,
                        note_text: noteText,
                        rubric_category_id: categoryId,
                        is_objection: isObjection,
                        objection_type_id: isObjection ? objectionTypeId : null,
                        objection_outcome: isObjection ? state.objectionOutcome : null,
                    }),
                });

                if (!response.ok) {
                    throw new Error('Failed to save note');
                }

                const note = await response.json();
                state.notes.push(note);
                state.notes.sort((a, b) => a.line_index_start - b.line_index_start);
                updateTranscriptNoteIndicators();
                closeAddNoteModal();
            } catch (error) {
                console.error('Error saving note:', error);
                alert('Failed to save note. Please try again.');
            } finally {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save Note';
            }
        }

        // ========================================
        // Why No Appointment Modal
        // ========================================
        function showWhyNoAppointmentModal() {
            document.querySelectorAll('.no-appt-objection-checkbox').forEach(cb => cb.checked = false);
            document.getElementById('no-appt-notes').value = '';
            document.getElementById('why-no-appointment-modal').classList.remove('hidden');
        }

        function closeWhyNoAppointmentModal() {
            document.getElementById('why-no-appointment-modal').classList.add('hidden');
        }

        async function saveNoAppointmentReason() {
            const checkboxes = document.querySelectorAll('.no-appt-objection-checkbox:checked');
            const objectionTypeIds = Array.from(checkboxes).map(cb => parseInt(cb.value));
            const notes = document.getElementById('no-appt-notes').value.trim();

            if (objectionTypeIds.length === 0) {
                alert('Please select at least one objection type.');
                return;
            }

            const saveBtn = document.getElementById('save-no-appt-btn');
            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving...';

            try {
                const response = await fetch(`/manager/calls/${state.callId}/no-appointment`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        objection_type_ids: objectionTypeIds,
                        notes: notes,
                    }),
                });

                if (!response.ok) {
                    throw new Error('Failed to save');
                }

                closeWhyNoAppointmentModal();
                saveGrade('submitted');
            } catch (error) {
                console.error('Error saving no-appointment reason:', error);
                alert('Failed to save. Please try again.');
            } finally {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Continue to Submit';
            }
        }

        function skipNoAppointment() {
            closeWhyNoAppointmentModal();
            saveGrade('submitted');
        }
    </script>
</body>
</html>
