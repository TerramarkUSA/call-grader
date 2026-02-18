<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Call Review - Call Grader</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        [x-cloak] { display: none !important; }
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
        #rep-select,
        #project-select,
        #outcome-select,
        #appointment-quality-select {
            text-align: center;
            text-align-last: center;
        }
        /* Drag selection styles */
        .utterance-selecting {
            background-color: rgb(219, 234, 254) !important;
            box-shadow: inset 0 0 0 2px rgb(59, 130, 246);
        }
        .utterance-drag-start {
            transform: scale(1.01);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
            z-index: 10;
        }
        .add-note-btn.dragging {
            background-color: rgb(59, 130, 246) !important;
            color: white !important;
            border-color: rgb(59, 130, 246) !important;
            opacity: 1 !important;
        }
        .selection-count-badge {
            position: fixed;
            background: rgb(59, 130, 246);
            color: white;
            padding: 4px 10px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 500;
            pointer-events: none;
            z-index: 100;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
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
                        @if(in_array(auth()->user()->role, ['system_admin', 'site_admin']))
                            <form method="POST" action="{{ route('admin.calls.ungrade', $call) }}"
                                  onsubmit="return confirm('This will permanently delete the grade and all scores. Coaching notes will be preserved. The call will return to the queue.\n\nContinue?')">
                                @csrf
                                <button type="submit" class="px-3 py-1 text-sm font-medium text-red-600 hover:text-red-800 hover:bg-red-50 rounded-full transition-colors">
                                    Clear Grade
                                </button>
                            </form>
                        @endif
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
                                <p class="text-sm text-gray-500">Click to hear snippet</p>
                                <p class="text-sm text-gray-500">+ to add note</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <button
                                    id="sync-to-audio-btn"
                                    type="button"
                                    class="text-sm px-3 py-1.5 rounded-lg border border-gray-200 hover:bg-gray-50 text-gray-600 flex items-center gap-1.5 transition-colors"
                                    title="Scroll transcript to current audio position"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                                    </svg>
                                    Sync Audio
                                </button>
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
                    </div>

                    <div id="transcript-container" class="flex-1 overflow-y-auto">
                        @php
                            // Merge consecutive same-speaker utterances
                            $mergedTranscript = [];
                            $currentGroup = null;

                            foreach ($transcript as $index => $utterance) {
                                $rawSpeaker = $utterance['speaker'] ?? 0;
                                
                                if ($currentGroup === null || $currentGroup['speaker'] !== $rawSpeaker) {
                                    // Start new group
                                    if ($currentGroup !== null) {
                                        $mergedTranscript[] = $currentGroup;
                                    }
                                    $currentGroup = [
                                        'speaker' => $rawSpeaker,
                                        'text' => $utterance['text'] ?? '',
                                        'start' => $utterance['start'] ?? 0,
                                        'end' => $utterance['end'] ?? 0,
                                        'indices' => [$index],
                                    ];
                                } else {
                                    // Append to current group
                                    $currentGroup['text'] .= ' ' . ($utterance['text'] ?? '');
                                    $currentGroup['end'] = $utterance['end'] ?? $currentGroup['end'];
                                    $currentGroup['indices'][] = $index;
                                }
                            }

                            if ($currentGroup !== null) {
                                $mergedTranscript[] = $currentGroup;
                            }
                        @endphp

                        @forelse($mergedTranscript as $groupIndex => $group)
                            @php
                                $rawSpeaker = $group['speaker'];
                                $effectiveSpeaker = $speakersSwapped ? (1 - $rawSpeaker) : $rawSpeaker;
                                $isRep = $effectiveSpeaker === 0;
                            @endphp
                            <div
                                class="utterance group relative p-3 pr-10 mb-3 rounded-lg {{ $isRep ? 'bg-blue-50 border-l-4 border-blue-400' : 'bg-green-50 border-l-4 border-green-400' }} cursor-pointer hover:bg-gray-100 transition-colors"
                                data-group-index="{{ $groupIndex }}"
                                data-indices="{{ json_encode($group['indices']) }}"
                                data-start="{{ $group['start'] }}"
                                data-end="{{ $group['end'] }}"
                                data-text="{{ $group['text'] }}"
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
                                        <span class="text-sm">{{ gmdate('i:s', (int)$group['start']) }}</span>
                                    </div>
                                    <!-- Note indicator -->
                                    <span class="note-indicator hidden text-blue-500">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M18 13V5a2 2 0 00-2-2H4a2 2 0 00-2 2v8a2 2 0 002 2h3l3 3 3-3h3a2 2 0 002-2z"/>
                                        </svg>
                                    </span>
                                </div>

                                <!-- Text -->
                                <p class="text-sm text-gray-700 leading-relaxed">{{ $group['text'] }}</p>

                                <!-- Add Note Button (Gutter) -->
                                <button
                                    type="button"
                                    class="add-note-btn absolute right-2 top-1/2 -translate-y-1/2 opacity-0 group-hover:opacity-100 bg-white shadow-sm border border-gray-200 rounded-full p-1.5 hover:bg-blue-500 hover:text-white hover:border-blue-500 text-gray-400 transition-all"
                                    title="Click to add note • Drag to select multiple"
                                    data-group-index="{{ $groupIndex }}"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                    </svg>
                                </button>
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
                            <div class="text-center">
                                <label class="block text-xs font-medium text-gray-500 mb-1">Rep Name</label>
                                <select
                                    id="rep-select"
                                    class="w-full text-sm text-center border border-gray-200 rounded-lg px-2 py-1.5 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                >
                                    <option value="">Select</option>
                                    @foreach($reps as $rep)
                                        <option value="{{ $rep->id }}" {{ $call->rep_id == $rep->id ? 'selected' : '' }}>
                                            {{ $rep->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="text-center">
                                <label class="block text-xs font-medium text-gray-500 mb-1">Project</label>
                                <select
                                    id="project-select"
                                    class="w-full text-sm text-center border border-gray-200 rounded-lg px-2 py-1.5 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                >
                                    <option value="">Select</option>
                                    @foreach($projects as $project)
                                        <option value="{{ $project->id }}" {{ $call->project_id == $project->id ? 'selected' : '' }}>
                                            {{ $project->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="text-center">
                                <label class="block text-xs font-medium text-gray-500 mb-1">
                                    Outcome
                                    @if($outcomeFromSalesforce)
                                        <span id="sf-indicator" class="text-xs text-blue-500 ml-1" title="Pre-filled from Salesforce">SF</span>
                                    @endif
                                </label>
                                <select
                                    id="outcome-select"
                                    class="w-full text-sm text-center border border-gray-200 rounded-lg px-2 py-1.5 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                >
                                    <option value="">-- Select Outcome --</option>
                                    <option value="appointment_set" {{ $suggestedOutcome == 'appointment_set' ? 'selected' : '' }}>Appointment Set</option>
                                    <option value="no_appointment" {{ $suggestedOutcome == 'no_appointment' ? 'selected' : '' }}>No Appointment</option>
                                </select>
                            </div>
                        </div>
                        <!-- Appointment Quality (only shown when outcome = appointment_set) -->
                        <div id="appointment-quality-row" class="mt-3 text-center {{ $suggestedOutcome == 'appointment_set' ? '' : 'hidden' }}">
                            <label class="block text-xs font-medium text-gray-500 mb-1">Appointment Quality</label>
                            <select
                                id="appointment-quality-select"
                                class="w-full text-sm text-center border border-gray-200 rounded-lg px-2 py-1.5 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            >
                                <option value="">Select</option>
                                <option value="solid" {{ ($existingGrade?->appointment_quality ?? '') == 'solid' ? 'selected' : '' }}>Solid - Enthusiastic, confirmed, likely to show</option>
                                <option value="tentative" {{ ($existingGrade?->appointment_quality ?? '') == 'tentative' ? 'selected' : '' }}>Tentative - Agreed but hesitant, may need confirmation</option>
                                <option value="backed_in" {{ ($existingGrade?->appointment_quality ?? '') == 'backed_in' ? 'selected' : '' }}>Backed In - Reluctantly agreed, high no-show risk</option>
                            </select>
                        </div>

                        <!-- Salesforce Milestones -->
                        @if($sfData['synced_at'])
                        <div class="mt-4 pt-3 border-t border-gray-200">
                            <p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-2">Salesforce Status</p>
                            <div class="flex items-center justify-center gap-4 text-sm">
                                <span class="{{ $sfData['appointment_made'] ? 'text-green-600' : 'text-gray-400' }}">
                                    {{ $sfData['appointment_made'] ? '✓' : '—' }} Appt
                                </span>
                                <span class="{{ $sfData['toured_property'] ? 'text-green-600' : 'text-gray-400' }}">
                                    {{ $sfData['toured_property'] ? '✓' : '—' }} Toured
                                </span>
                                <span class="{{ $sfData['opportunity_created'] ? 'text-green-600' : 'text-gray-400' }}">
                                    {{ $sfData['opportunity_created'] ? '✓' : '—' }} Contract
                                </span>
                            </div>
                            <p class="text-xs text-gray-400 mt-1 text-center">
                                Synced: {{ $sfData['synced_at']->format('M j, g:i A') }}
                            </p>
                        </div>
                        <div id="sf-conflict-notice" class="hidden mt-2 p-2 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-700 text-center"></div>
                        @endif
                    </div>
                </div>

                <!-- Rubric Grading -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden flex-1 flex flex-col">
                    <div class="border-b">
                        <div class="flex">
                            <button id="rubric-tab-btn" onclick="switchRubricTab('rubric')" class="flex-1 px-5 py-2.5 text-sm font-medium text-gray-900 border-b-2 border-blue-500 bg-white cursor-pointer transition-colors">
                                Rubric Grading
                            </button>
                            <button id="notes-tab-btn" onclick="switchRubricTab('notes')" class="flex-1 px-5 py-2.5 text-sm font-medium text-gray-500 hover:text-gray-700 bg-gray-50 cursor-pointer border-b-2 border-transparent transition-colors">
                                Notes (<span id="notes-tab-count">0</span>)
                            </button>
                        </div>
                    </div>

                    <!-- Notes Panel (hidden by default) -->
                    <div id="notes-panel" class="px-5 py-4 flex-1 overflow-y-auto min-h-0 hidden">
                        <div class="mb-3">
                            <h3 class="text-base font-semibold text-gray-900">Coaching Notes</h3>
                            <p class="text-sm text-gray-500">Click a snippet note to jump to that moment</p>
                        </div>
                        <div id="notes-list" class="space-y-3">
                            <p class="text-sm text-gray-400 text-center py-4">No notes yet. Add notes from the transcript.</p>
                        </div>
                    </div>

                    <!-- Sales Call Evaluation -->
                    <div id="rubric-panel" class="px-5 py-4 flex-1 overflow-y-auto min-h-0">
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
                                <div class="category-card border border-gray-200 rounded-lg p-3" data-category-id="{{ $category->id }}" data-weight="{{ $category->weight }}">
                                    <div class="mb-2">
                                        <div class="flex items-center justify-between">
                                            <h4 class="font-medium text-gray-900 text-sm">{{ $category->name }}</h4>
                                            <span class="text-xs text-gray-400 font-medium">{{ intval($category->weight * 100) }}%</span>
                                        </div>
                                        @if($category->description)
                                            <p class="text-sm text-gray-600 mt-1">{{ $category->description }}</p>
                                        @endif
                                    </div>

                                    <!-- Score Buttons -->
                                    <div class="flex gap-2">
                                        @for($score = 1; $score <= 4; $score++)
                                            <button
                                                type="button"
                                                class="score-btn flex-1 py-2 text-center rounded-lg border-2 border-gray-200 text-gray-700 font-medium hover:border-blue-300 hover:bg-blue-50 transition-all text-sm"
                                                data-score="{{ $score }}"
                                                @if($category->scoring_criteria && isset($category->scoring_criteria[$score]))
                                                    title="{{ $category->scoring_criteria[$score] }}"
                                                @endif
                                            >
                                                {{ $score }}
                                            </button>
                                        @endfor
                                    </div>

                                    <!-- Training & Scoring Details Accordion -->
                                    @if($category->training_reference || $category->scoring_criteria)
                                        <div class="mt-2">
                                            <button
                                                type="button"
                                                class="toggle-training-details text-xs text-gray-500 hover:text-gray-700 flex items-center gap-1 transition-colors"
                                                onclick="toggleTrainingDetails({{ $category->id }})"
                                            >
                                                <svg
                                                    id="training-chevron-{{ $category->id }}"
                                                    class="w-3 h-3 transition-transform"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    viewBox="0 0 24 24"
                                                >
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                                </svg>
                                                <span id="training-toggle-text-{{ $category->id }}">Scoring guide</span>
                                            </button>
                                            <div
                                                id="training-details-{{ $category->id }}"
                                                class="hidden mt-2 pl-3 border-l-2 border-blue-300 text-sm text-gray-600 text-left space-y-3"
                                            >
                                                @if($category->training_reference)
                                                    <div>
                                                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Training Tip</p>
                                                        <p class="text-sm text-gray-600">{{ $category->training_reference }}</p>
                                                    </div>
                                                @endif
                                                @if($category->scoring_criteria)
                                                    <div>
                                                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">What Each Score Means</p>
                                                        <div class="space-y-1.5">
                                                            @foreach($category->scoring_criteria as $scoreVal => $criteria)
                                                                <div class="flex gap-2">
                                                                    <span class="inline-flex items-center justify-center w-5 h-5 rounded text-xs font-bold shrink-0
                                                                        {{ $scoreVal == 4 ? 'bg-green-100 text-green-700' : '' }}
                                                                        {{ $scoreVal == 3 ? 'bg-blue-100 text-blue-700' : '' }}
                                                                        {{ $scoreVal == 2 ? 'bg-amber-100 text-amber-700' : '' }}
                                                                        {{ $scoreVal == 1 ? 'bg-red-100 text-red-700' : '' }}
                                                                    ">{{ $scoreVal }}</span>
                                                                    <span class="text-xs text-gray-600 leading-relaxed">{{ $criteria }}</span>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    @endif
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
                                    <h4 class="text-sm font-medium text-green-700 mb-2">Do These</h4>
                                    @php
                                        $positiveLabels = [
                                            'Asked discovery questions',
                                            'Captured contact info',
                                            'Gave information, Got information',
                                            'Asked for appointment',
                                            'Explained full sale',
                                            'Confirmed next steps',
                                            'Sold Company'
                                        ];
                                    @endphp
                                    @foreach($positiveCheckpoints as $index => $checkpoint)
                                        <div class="checkpoint-row flex items-center justify-between py-2 px-2 border-b border-gray-100 transition-colors" data-checkpoint-id="{{ $checkpoint->id }}" data-type="positive">
                                            <span class="text-sm text-gray-700 pr-2">{{ $positiveLabels[$index] ?? $checkpoint->name }}</span>
                                            <div class="flex gap-1 flex-shrink-0">
                                                <button type="button" class="checkpoint-btn px-2 py-1 text-xs font-medium rounded border border-gray-200 text-gray-400 hover:border-green-400 hover:text-green-600 transition-colors" data-value="1">Yes</button>
                                                <button type="button" class="checkpoint-btn px-2 py-1 text-xs font-medium rounded border border-gray-200 text-gray-400 hover:border-red-400 hover:text-red-600 transition-colors" data-value="0">No</button>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            @if(count($negativeCheckpoints) > 0)
                                <div>
                                    <h4 class="text-sm font-medium text-red-700 mb-2">Don't Do These</h4>
                                    @php
                                        $negativeLabels = [
                                            'Product vomit',
                                            'Over-informed',
                                            'Lost control',
                                            'Talked past the close'
                                        ];
                                    @endphp
                                    @foreach($negativeCheckpoints as $index => $checkpoint)
                                        <div class="checkpoint-row flex items-center justify-between py-2 px-2 border-b border-gray-100 transition-colors" data-checkpoint-id="{{ $checkpoint->id }}" data-type="negative">
                                            <span class="text-sm text-gray-700 pr-2">{{ $negativeLabels[$index] ?? $checkpoint->name }}</span>
                                            <div class="flex gap-1 flex-shrink-0">
                                                <button type="button" class="checkpoint-btn px-2 py-1 text-xs font-medium rounded border border-gray-200 text-gray-400 hover:border-red-400 hover:text-red-600 transition-colors" data-value="1">Yes</button>
                                                <button type="button" class="checkpoint-btn px-2 py-1 text-xs font-medium rounded border border-gray-200 text-gray-400 hover:border-green-400 hover:text-green-600 transition-colors" data-value="0">No</button>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                <!-- Overall Call Notes Section -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="px-5 py-3 border-b">
                        <h3 class="text-lg font-semibold text-gray-900">Overall Call Notes</h3>
                        <p class="text-sm text-gray-500">General feedback about this call</p>
                    </div>
                    <div class="px-5 py-4">
                        <textarea
                            id="overall-notes-textarea"
                            rows="4"
                            class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
                            placeholder="Enter your overall thoughts about this call..."
                        ></textarea>
                    </div>
                </div>

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

                {{-- Share with Rep - disabled for now
                @if($existingGrade && $existingGrade->status === 'submitted')
                    <x-share-with-rep-modal :call="$call" :grade="$existingGrade" />
                @endif
                --}}

                <!-- Skip Call Link -->
                @if(!$existingGrade || $existingGrade->status !== 'submitted')
                <div class="text-center mt-3">
                    <button type="button" id="grading-skip-toggle" class="text-sm text-orange-600 hover:text-orange-800">
                        Skip this call
                    </button>
                </div>

                <div id="grading-skip-panel" class="bg-white rounded-xl shadow-sm overflow-hidden mt-3" style="display: none;">
                    <div class="px-5 py-4">
                        <h3 class="text-sm font-semibold text-gray-900 mb-3">Why are you skipping?</h3>
                        <div class="space-y-2 mb-4">
                            <label class="flex items-center gap-3 p-2 rounded-lg hover:bg-gray-50 cursor-pointer">
                                <input type="radio" name="grading_skip_reason" value="not_gradeable" class="text-orange-600 focus:ring-orange-500">
                                <span class="text-sm text-gray-700">Not Gradeable</span>
                            </label>
                            <label class="flex items-center gap-3 p-2 rounded-lg hover:bg-gray-50 cursor-pointer">
                                <input type="radio" name="grading_skip_reason" value="wrong_call_type" class="text-orange-600 focus:ring-orange-500">
                                <span class="text-sm text-gray-700">Wrong Call Type</span>
                            </label>
                            <label class="flex items-center gap-3 p-2 rounded-lg hover:bg-gray-50 cursor-pointer">
                                <input type="radio" name="grading_skip_reason" value="poor_audio" class="text-orange-600 focus:ring-orange-500">
                                <span class="text-sm text-gray-700">Poor Audio Quality</span>
                            </label>
                            <label class="flex items-center gap-3 p-2 rounded-lg hover:bg-gray-50 cursor-pointer">
                                <input type="radio" name="grading_skip_reason" value="not_a_real_call" class="text-orange-600 focus:ring-orange-500">
                                <span class="text-sm text-gray-700">Not a Real Call</span>
                            </label>
                            <label class="flex items-center gap-3 p-2 rounded-lg hover:bg-gray-50 cursor-pointer">
                                <input type="radio" name="grading_skip_reason" value="too_short" class="text-orange-600 focus:ring-orange-500">
                                <span class="text-sm text-gray-700">Too Short to Grade</span>
                            </label>
                            <label class="flex items-center gap-3 p-2 rounded-lg hover:bg-gray-50 cursor-pointer">
                                <input type="radio" name="grading_skip_reason" value="other" class="text-orange-600 focus:ring-orange-500">
                                <span class="text-sm text-gray-700">Other</span>
                            </label>
                        </div>
                        <div class="flex gap-3">
                            <button type="button" id="grading-skip-confirm" disabled
                                class="flex-1 px-4 py-2 bg-orange-600 text-white rounded-xl hover:bg-orange-700 font-medium text-sm disabled:opacity-50 disabled:cursor-not-allowed">
                                Confirm Skip
                            </button>
                            <button type="button" id="grading-skip-cancel"
                                class="px-4 py-2 border border-gray-200 rounded-xl hover:bg-gray-50 text-sm">
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
                @endif
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

                <!-- Existing notes for this line -->
                <div id="modal-existing-notes" class="hidden px-6 py-3 border-b bg-blue-50/50">
                    <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">Existing Notes</p>
                    <div id="modal-existing-notes-list" class="space-y-2"></div>
                </div>

                <div class="px-6 py-4 space-y-4">
                    <div>
                        <label id="note-form-label" class="block text-sm font-medium text-gray-700 mb-1">Coaching Note</label>
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

                    <div class="border rounded-xl p-4 bg-amber-50/50">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" id="is-golden-checkbox" class="rounded border-gray-300 text-amber-500 focus:ring-amber-500"/>
                            <span class="text-sm font-medium text-gray-700">⭐ Golden Moment</span>
                        </label>
                        <p id="golden-helper-text" class="mt-1.5 pl-6 text-xs text-gray-400 hidden">This note will be visible to all managers in your office.</p>
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

        window.addEventListener('beforeunload', () => {
            const data = JSON.stringify({
                page_seconds: getPageSeconds(),
                playback_seconds: Math.floor(state.playbackSeconds),
            });
            navigator.sendBeacon(
                '{{ route("manager.calls.page-time", $call) }}',
                new Blob([data], { type: 'application/json' })
            );
        });

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
            repId: {{ $call->rep_id ?? 'null' }},
            projectId: {{ $call->project_id ?? 'null' }},
            outcome: @json($suggestedOutcome),
            outcomeFromSalesforce: {{ $outcomeFromSalesforce ? 'true' : 'false' }},
            sfAppointmentMade: {{ $sfData['appointment_made'] ? 'true' : 'false' }},
            overallNotes: '',
            overallNoteId: null,
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

        // Drag-to-select state
        let isDragging = false;
        let dragStartIndex = null;
        let dragCurrentIndex = null;
        let selectionBadge = null;

        utterances.forEach((utterance, index) => {
            // Click utterance body → seek audio
            utterance.addEventListener('click', (e) => {
                // Ignore if clicking the add note button or during drag
                if (e.target.closest('.add-note-btn') || isDragging) return;
                
                const start = parseFloat(utterance.dataset.start);
                audio.currentTime = start;
                audio.play();
            });

            // Drag-to-select on (+) button
            const addNoteBtn = utterance.querySelector('.add-note-btn');
            if (addNoteBtn) {
                // Mouse down → start potential drag
                addNoteBtn.addEventListener('mousedown', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    isDragging = true;
                    dragStartIndex = index;
                    dragCurrentIndex = index;
                    
                    // Visual feedback - lift effect
                    utterance.classList.add('utterance-drag-start', 'utterance-selecting');
                    addNoteBtn.classList.add('dragging');
                    
                    // Create selection badge
                    selectionBadge = document.createElement('div');
                    selectionBadge.className = 'selection-count-badge';
                    selectionBadge.textContent = '1 snippet';
                    document.body.appendChild(selectionBadge);
                    updateBadgePosition(e);
                });
            }

            // Track mouse entering utterances during drag
            utterance.addEventListener('mouseenter', () => {
                if (isDragging && dragStartIndex !== null) {
                    dragCurrentIndex = index;
                    updateDragSelection();
                }
            });
        });

        // Update selection highlighting
        function updateDragSelection() {
            const minIdx = Math.min(dragStartIndex, dragCurrentIndex);
            const maxIdx = Math.max(dragStartIndex, dragCurrentIndex);
            const count = maxIdx - minIdx + 1;
            
            utterances.forEach((u, i) => {
                if (i >= minIdx && i <= maxIdx) {
                    u.classList.add('utterance-selecting');
                } else {
                    u.classList.remove('utterance-selecting');
                }
                // Keep drag-start effect only on start utterance
                if (i === dragStartIndex) {
                    u.classList.add('utterance-drag-start');
                } else {
                    u.classList.remove('utterance-drag-start');
                }
            });
            
            // Update badge
            if (selectionBadge) {
                selectionBadge.textContent = `${count} snippet${count > 1 ? 's' : ''}`;
            }
        }

        // Update badge position near cursor
        function updateBadgePosition(e) {
            if (selectionBadge) {
                selectionBadge.style.left = (e.clientX + 15) + 'px';
                selectionBadge.style.top = (e.clientY - 10) + 'px';
            }
        }

        // Track mouse movement for badge position
        document.addEventListener('mousemove', (e) => {
            if (isDragging) {
                updateBadgePosition(e);
            }
        });

        // Mouse up → end drag and open modal
        document.addEventListener('mouseup', (e) => {
            if (!isDragging || dragStartIndex === null) return;
            
            const minIdx = Math.min(dragStartIndex, dragCurrentIndex);
            const maxIdx = Math.max(dragStartIndex, dragCurrentIndex);
            
            // Collect all selected utterances data
            const selectedUtterances = [];
            let combinedText = [];
            let startTime = null;
            let endTime = null;
            let allIndices = [];
            
            for (let i = minIdx; i <= maxIdx; i++) {
                const u = utterances[i];
                const indices = JSON.parse(u.dataset.indices || '[]');
                allIndices = allIndices.concat(indices);
                combinedText.push(u.dataset.text);
                
                const uStart = parseFloat(u.dataset.start);
                const uEnd = parseFloat(u.dataset.end);
                
                if (startTime === null || uStart < startTime) startTime = uStart;
                if (endTime === null || uEnd > endTime) endTime = uEnd;
            }
            
            // Clear visual states
            utterances.forEach(u => {
                u.classList.remove('utterance-selecting', 'utterance-drag-start');
                const btn = u.querySelector('.add-note-btn');
                if (btn) btn.classList.remove('dragging');
            });
            
            // Remove badge
            if (selectionBadge) {
                selectionBadge.remove();
                selectionBadge = null;
            }
            
            // Open modal with combined data
            openAddNoteModal({
                lineIndexStart: allIndices[0] ?? 0,
                lineIndexEnd: allIndices[allIndices.length - 1] ?? 0,
                timestampStart: startTime,
                timestampEnd: endTime,
                text: combinedText.join(' ... '),
            });
            
            // Reset drag state
            isDragging = false;
            dragStartIndex = null;
            dragCurrentIndex = null;
        });

        // ========================================
        // Teleprompter-style Auto-Scroll
        // ========================================
        let scrollAnimationId = null;
        let isProgrammaticScroll = false;
        let userIsManuallyScrolling = false;
        let manualScrollTimeout = null;
        const transcriptContainer = document.getElementById('transcript-container');

        // Detect manual scroll — back off auto-scroll for 2 seconds
        transcriptContainer.addEventListener('scroll', () => {
            if (isProgrammaticScroll) return;
            userIsManuallyScrolling = true;
            clearTimeout(manualScrollTimeout);
            manualScrollTimeout = setTimeout(() => {
                userIsManuallyScrolling = false;
            }, 2000);
        }, { passive: true });

        function scrollToUtterance(element, force) {
            if (!element) return;
            if (userIsManuallyScrolling && !force) return;

            const containerHeight = transcriptContainer.clientHeight;
            const containerRect = transcriptContainer.getBoundingClientRect();
            const elementRect = element.getBoundingClientRect();
            const elementRelative = elementRect.top - containerRect.top;
            const positionPercent = elementRelative / containerHeight;

            // Inside comfort zone (20%–70%)? Do nothing.
            if (!force && positionPercent >= 0.20 && positionPercent <= 0.70) return;

            // Target: position utterance at 30% from top of container
            const targetScrollTop = transcriptContainer.scrollTop + elementRelative - (containerHeight * 0.30);
            animateScroll(targetScrollTop, 300);
        }

        function animateScroll(targetScrollTop, duration) {
            if (scrollAnimationId) cancelAnimationFrame(scrollAnimationId);

            const startScrollTop = transcriptContainer.scrollTop;
            const distance = targetScrollTop - startScrollTop;
            if (Math.abs(distance) < 1) return; // Already there
            const startTime = performance.now();

            function step(currentTime) {
                const elapsed = currentTime - startTime;
                const progress = Math.min(elapsed / duration, 1);
                const eased = 1 - Math.pow(1 - progress, 3); // ease-out cubic

                isProgrammaticScroll = true;
                transcriptContainer.scrollTop = startScrollTop + (distance * eased);

                if (progress < 1) {
                    scrollAnimationId = requestAnimationFrame(step);
                } else {
                    scrollAnimationId = null;
                    setTimeout(() => { isProgrammaticScroll = false; }, 50);
                }
            }

            scrollAnimationId = requestAnimationFrame(step);
        }

        function highlightCurrentUtterance(currentTime) {
            utterances.forEach((utterance, index) => {
                const start = parseFloat(utterance.dataset.start);
                const end = parseFloat(utterance.dataset.end);

                if (currentTime >= start && currentTime < end) {
                    if (currentUtteranceIndex !== index) {
                        utterances.forEach(u => u.classList.remove('utterance-active'));
                        utterance.classList.add('utterance-active');
                        scrollToUtterance(utterance, false);
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
                    const newValue = value === '1';
                    const currentValue = state.checkpointResponses[checkpointId];

                    // Toggle: if clicking the same value, deselect; otherwise select
                    if (currentValue === newValue) {
                        delete state.checkpointResponses[checkpointId];
                        updateCheckpointDisplay(row, null, type);
                    } else {
                        state.checkpointResponses[checkpointId] = newValue;
                        updateCheckpointDisplay(row, newValue, type);
                    }
                });
            });

            if (state.checkpointResponses[checkpointId] !== undefined) {
                updateCheckpointDisplay(row, state.checkpointResponses[checkpointId], type);
            }
        });

        function updateCheckpointDisplay(row, observed, type) {
            const buttons = row.querySelectorAll('.checkpoint-btn');
            
            // Reset row background and button styles
            row.classList.remove('bg-green-50', 'bg-red-50');

            buttons.forEach(btn => {
                const value = btn.dataset.value === '1';
                // Reset button styles
                btn.classList.remove('text-green-600', 'text-red-600', 'text-gray-400', 'bg-green-100', 'bg-red-100', 'border-green-400', 'border-red-400', 'border-gray-200');
                btn.classList.add('border-gray-200');

                if (observed === null || observed === undefined) {
                    // Neutral state - neither selected
                    btn.classList.add('text-gray-400');
                } else if (value && observed === true) {
                    // Yes button selected
                    btn.classList.remove('border-gray-200');
                    if (type === 'positive') {
                        // "Do These" + Yes = good (green)
                        btn.classList.add('text-green-600', 'bg-green-100', 'border-green-400');
                        row.classList.add('bg-green-50');
                    } else {
                        // "Don't Do These" + Yes = bad (red) - they DID the bad thing
                        btn.classList.add('text-red-600', 'bg-red-100', 'border-red-400');
                        row.classList.add('bg-red-50');
                    }
                } else if (!value && observed === false) {
                    // No button selected
                    btn.classList.remove('border-gray-200');
                    if (type === 'positive') {
                        // "Do These" + No = bad (red) - they didn't do the good thing
                        btn.classList.add('text-red-600', 'bg-red-100', 'border-red-400');
                        row.classList.add('bg-red-50');
                    } else {
                        // "Don't Do These" + No = good (green) - they avoided the bad thing
                        btn.classList.add('text-green-600', 'bg-green-100', 'border-green-400');
                        row.classList.add('bg-green-50');
                    }
                } else {
                    // Unselected button
                    btn.classList.add('text-gray-400');
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
                        rep_id: state.repId,
                        project_id: state.projectId,
                        outcome: state.outcome,
                        playback_seconds: Math.floor(state.playbackSeconds),
                        page_seconds: getPageSeconds(),
                        status: status,
                    }),
                });

                const data = await response.json();

                if (data.success) {
                    // Save overall notes if there's content
                    const overallNotesText = document.getElementById('overall-notes-textarea').value.trim();
                    if (overallNotesText) {
                        await saveOverallNote(overallNotesText);
                    }
                    
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

        async function saveOverallNote(noteText) {
            try {
                const url = state.overallNoteId 
                    ? `/manager/notes/${state.overallNoteId}`
                    : `/manager/calls/${state.callId}/notes`;
                const method = state.overallNoteId ? 'PATCH' : 'POST';
                
                const response = await fetch(url, {
                    method: method,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        grade_id: state.gradeId,
                        note_text: noteText,
                        line_index_start: null,
                        line_index_end: null,
                        timestamp_start: null,
                        timestamp_end: null,
                        transcript_text: null,
                        is_objection: false,
                    }),
                });

                if (response.ok) {
                    const note = await response.json();
                    state.overallNoteId = note.id;
                    state.overallNotes = noteText;
                }
            } catch (error) {
                console.error('Error saving overall note:', error);
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

            // Check for unevaluated checkpoints
            const totalCheckpoints = document.querySelectorAll('.checkpoint-row').length;
            const evaluatedCheckpoints = Object.keys(state.checkpointResponses).length;
            const unevaluatedCount = totalCheckpoints - evaluatedCheckpoints;

            if (unevaluatedCount > 0) {
                if (!confirm(`${unevaluatedCount} checkpoint${unevaluatedCount > 1 ? 's' : ''} not evaluated - continue anyway?`)) {
                    return;
                }
            }

            // Require outcome selection
            if (!state.outcome) {
                alert('Please select an outcome before submitting.');
                document.getElementById('outcome-select').focus();
                return;
            }

            if (state.outcome === 'appointment_set') {
                // Require appointment quality for appointments
                if (!state.appointmentQuality) {
                    alert('Please select an appointment quality (Solid, Tentative, or Backed In).');
                    document.getElementById('appointment-quality-select').focus();
                    return;
                }
                saveGrade('submitted');
            } else {
                // no_appointment → show Why No Appointment modal (required)
                showWhyNoAppointmentModal();
            }
        });

        // ========================================
        // Training Details Accordion
        // ========================================
        function toggleTrainingDetails(categoryId) {
            const detailsDiv = document.getElementById(`training-details-${categoryId}`);
            const chevron = document.getElementById(`training-chevron-${categoryId}`);
            const toggleText = document.getElementById(`training-toggle-text-${categoryId}`);
            
            if (detailsDiv && chevron && toggleText) {
                const isHidden = detailsDiv.classList.contains('hidden');
                
                // Close all other training details first
                document.querySelectorAll('[id^="training-details-"]').forEach(el => {
                    if (el.id !== `training-details-${categoryId}`) {
                        el.classList.add('hidden');
                        const otherId = el.id.replace('training-details-', '');
                        const otherChevron = document.getElementById(`training-chevron-${otherId}`);
                        const otherText = document.getElementById(`training-toggle-text-${otherId}`);
                        if (otherChevron) otherChevron.style.transform = 'rotate(0deg)';
                        if (otherText) otherText.textContent = 'Scoring guide';
                    }
                });
                
                if (isHidden) {
                    detailsDiv.classList.remove('hidden');
                    chevron.style.transform = 'rotate(90deg)';
                    toggleText.textContent = 'Hide guide';
                } else {
                    detailsDiv.classList.add('hidden');
                    chevron.style.transform = 'rotate(0deg)';
                    toggleText.textContent = 'Scoring guide';
                }
            }
        }

        // ========================================
        // Initialize
        // ========================================
        updateOverallScore();
        loadNotes();

        // ========================================
        // Sync to Audio
        // ========================================
        function syncTranscriptToAudio() {
            userIsManuallyScrolling = false;
            clearTimeout(manualScrollTimeout);
            if (currentUtteranceIndex >= 0 && utterances[currentUtteranceIndex]) {
                scrollToUtterance(utterances[currentUtteranceIndex], true);
            }
        }

        document.getElementById('sync-to-audio-btn').addEventListener('click', syncTranscriptToAudio);

        // ========================================
        // Swap Speakers (Multichannel)
        // ========================================
        const swapSpeakersBtn = document.getElementById('swap-speakers-btn');
        if (swapSpeakersBtn) {
            swapSpeakersBtn.addEventListener('click', swapSpeakers);
        }

        // ========================================
        // Call Details Dropdowns
        // ========================================
        const repSelect = document.getElementById('rep-select');
        const projectSelect = document.getElementById('project-select');
        const outcomeSelect = document.getElementById('outcome-select');
        const appointmentQualitySelect = document.getElementById('appointment-quality-select');
        const appointmentQualityRow = document.getElementById('appointment-quality-row');

        repSelect.addEventListener('change', (e) => {
            state.repId = e.target.value ? parseInt(e.target.value) : null;
        });

        projectSelect.addEventListener('change', (e) => {
            state.projectId = e.target.value ? parseInt(e.target.value) : null;
        });

        outcomeSelect.addEventListener('change', (e) => {
            state.outcome = e.target.value || null;

            // Remove SF indicator when manager manually changes
            const sfIndicator = document.getElementById('sf-indicator');
            if (sfIndicator) sfIndicator.remove();

            // Show/hide conflict notice
            const conflictNotice = document.getElementById('sf-conflict-notice');
            if (conflictNotice) {
                if (state.outcome === 'appointment_set' && !state.sfAppointmentMade) {
                    conflictNotice.textContent = "Note: Salesforce doesn't show an appointment yet — it may not be updated.";
                    conflictNotice.classList.remove('hidden');
                } else if (state.outcome !== 'appointment_set' && state.sfAppointmentMade) {
                    conflictNotice.textContent = "Note: Salesforce shows an appointment was made.";
                    conflictNotice.classList.remove('hidden');
                } else {
                    conflictNotice.classList.add('hidden');
                }
            }

            // Show/hide appointment quality based on outcome
            if (e.target.value === 'appointment_set') {
                appointmentQualityRow.classList.remove('hidden');
            } else {
                appointmentQualityRow.classList.add('hidden');
                state.appointmentQuality = null;
                appointmentQualitySelect.value = '';
            }
        });

        appointmentQualitySelect.addEventListener('change', (e) => {
            state.appointmentQuality = e.target.value || null;
        });

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

                // Update background colors
                utterance.classList.remove('bg-blue-50', 'border-blue-400', 'bg-green-50', 'border-green-400');
                if (isRep) {
                    utterance.classList.add('bg-blue-50', 'border-blue-400');
                } else {
                    utterance.classList.add('bg-green-50', 'border-green-400');
                }

                const label = utterance.querySelector('.speaker-label');
                const speakerIcon = utterance.querySelector('.flex.items-center.gap-1\\.5 svg');

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

                if (speakerIcon) {
                    speakerIcon.classList.remove('text-blue-500', 'text-green-500');
                    speakerIcon.classList.add(isRep ? 'text-blue-500' : 'text-green-500');
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
                const allNotes = await response.json();
                
                // Separate overall notes (no line_index_start) from snippet notes
                const overallNote = allNotes.find(n => n.line_index_start === null);
                if (overallNote) {
                    state.overallNotes = overallNote.note_text;
                    state.overallNoteId = overallNote.id;
                    document.getElementById('overall-notes-textarea').value = overallNote.note_text;
                }
                
                // Regular notes are those with line_index_start
                state.notes = allNotes.filter(n => n.line_index_start !== null);
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

            // Check each utterance block for notes matching any of its indices
            document.querySelectorAll('.utterance').forEach(utterance => {
                const indices = JSON.parse(utterance.dataset.indices || '[]');
                let hasNote = false;
                let hasObjection = false;

                state.notes.forEach(note => {
                    if (indices.includes(note.line_index_start)) {
                        if (note.is_objection) {
                            hasObjection = true;
                        } else {
                            hasNote = true;
                        }
                    }
                });

                if (hasObjection) {
                    utterance.classList.add('utterance-has-objection');
                } else if (hasNote) {
                    utterance.classList.add('utterance-has-note');
                }

                if (hasNote || hasObjection) {
                    const indicator = utterance.querySelector('.note-indicator');
                    if (indicator) indicator.classList.remove('hidden');
                }
            });

            renderNotesPanel();
        }

        // ========================================
        // Notes Tab & Panel
        // ========================================
        function switchRubricTab(tab) {
            const rubricPanel = document.getElementById('rubric-panel');
            const notesPanel = document.getElementById('notes-panel');
            const rubricBtn = document.getElementById('rubric-tab-btn');
            const notesBtn = document.getElementById('notes-tab-btn');

            if (tab === 'notes') {
                rubricPanel.classList.add('hidden');
                notesPanel.classList.remove('hidden');
                rubricBtn.className = 'flex-1 px-5 py-2.5 text-sm font-medium text-gray-500 hover:text-gray-700 bg-gray-50 cursor-pointer border-b-2 border-transparent transition-colors';
                notesBtn.className = 'flex-1 px-5 py-2.5 text-sm font-medium text-gray-900 border-b-2 border-blue-500 bg-white cursor-pointer transition-colors';
                renderNotesPanel();
            } else {
                notesPanel.classList.add('hidden');
                rubricPanel.classList.remove('hidden');
                notesBtn.className = 'flex-1 px-5 py-2.5 text-sm font-medium text-gray-500 hover:text-gray-700 bg-gray-50 cursor-pointer border-b-2 border-transparent transition-colors';
                rubricBtn.className = 'flex-1 px-5 py-2.5 text-sm font-medium text-gray-900 border-b-2 border-blue-500 bg-white cursor-pointer transition-colors';
            }
        }

        function renderNotesPanel() {
            const container = document.getElementById('notes-list');
            const allNotes = [];

            // Add overall note if exists
            if (state.overallNotes) {
                allNotes.push({ type: 'overall', note_text: state.overallNotes, id: state.overallNoteId });
            }

            // Add snippet notes sorted by position
            const snippets = [...state.notes].sort((a, b) => (a.line_index_start ?? 0) - (b.line_index_start ?? 0));
            snippets.forEach(n => allNotes.push({ type: 'snippet', ...n }));

            // Update tab count
            document.getElementById('notes-tab-count').textContent = allNotes.length;

            if (allNotes.length === 0) {
                container.innerHTML = '<p class="text-sm text-gray-400 text-center py-4">No notes yet. Add notes from the transcript.</p>';
                return;
            }

            container.innerHTML = allNotes.map(note => {
                if (note.type === 'overall') {
                    return `<div class="border border-purple-200 rounded-lg p-3 bg-purple-50/30">
                        <div class="flex items-center gap-2 mb-1.5">
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium bg-purple-100 text-purple-700">Overall</span>
                        </div>
                        <p class="text-sm text-gray-900">${escapeHtml(note.note_text)}</p>
                    </div>`;
                }

                const hasTimestamp = note.timestamp_start != null;
                const cursorClass = hasTimestamp ? 'cursor-pointer hover:border-blue-300 hover:shadow-sm' : '';
                const clickAttr = hasTimestamp ? `onclick="jumpToNote(${note.line_index_start}, ${note.timestamp_start})"` : '';

                let categoryTag = '';
                if (note.is_objection && note.objection_type) {
                    const outcomeIcon = note.objection_outcome === 'overcame' ? '&#10003;' : '&#10007;';
                    const outcomeColor = note.objection_outcome === 'overcame' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700';
                    categoryTag = `<span class="rounded-full px-2 py-0.5 text-xs font-medium ${outcomeColor}">${escapeHtml(note.objection_type.name)} ${outcomeIcon}</span>`;
                } else if (note.category) {
                    categoryTag = `<span class="rounded-full px-2 py-0.5 text-xs font-medium bg-blue-100 text-blue-700">${escapeHtml(note.category.name)}</span>`;
                }

                const timestampLabel = hasTimestamp ? `<span class="text-xs text-gray-400 ml-auto">${formatTime(note.timestamp_start)}</span>` : '';

                const transcriptSnippet = note.transcript_text
                    ? `<p class="text-xs text-gray-400 italic mb-1.5 line-clamp-2">"${escapeHtml(note.transcript_text)}"</p>`
                    : '';

                return `<div class="border border-gray-200 rounded-lg p-3 transition-all ${cursorClass}" ${clickAttr}>
                    ${transcriptSnippet}
                    <p class="text-sm text-gray-900 mb-2">${escapeHtml(note.note_text)}</p>
                    <div class="flex items-center gap-2 flex-wrap">
                        ${categoryTag}
                        ${note.is_objection ? '<span class="rounded-full px-2 py-0.5 text-xs font-medium bg-yellow-100 text-yellow-700">Objection</span>' : ''}
                        ${note.is_exemplar ? '<span class="rounded-full px-2 py-0.5 text-xs font-medium bg-amber-100 text-amber-700">⭐ Golden</span>' : ''}
                        ${timestampLabel}
                    </div>
                </div>`;
            }).join('');
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function jumpToNote(lineIndexStart, timestamp) {
            // Seek audio
            if (timestamp != null && !isNaN(timestamp)) {
                audio.currentTime = parseFloat(timestamp);
                audio.play();
            }

            // Find the utterance with this line index and scroll to it
            const utterances = document.querySelectorAll('.utterance');
            for (const utterance of utterances) {
                const indices = JSON.parse(utterance.dataset.indices || '[]');
                if (indices.includes(lineIndexStart)) {
                    // Highlight briefly
                    utterance.style.transition = 'background-color 0.3s';
                    utterance.style.backgroundColor = 'rgb(219 234 254)';
                    setTimeout(() => { utterance.style.backgroundColor = ''; }, 2000);

                    // Scroll transcript to this utterance
                    scrollToUtterance(utterance, true);
                    break;
                }
            }
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
            document.getElementById('is-golden-checkbox').checked = false;
            document.getElementById('golden-helper-text').classList.add('hidden');

            // Show existing notes for this line range
            const existingContainer = document.getElementById('modal-existing-notes');
            const existingList = document.getElementById('modal-existing-notes-list');
            const lineStart = selection.lineIndexStart;
            const lineEnd = selection.lineIndexEnd;
            const matchingNotes = state.notes.filter(n => {
                return n.line_index_start >= lineStart && n.line_index_start <= lineEnd;
            });

            if (matchingNotes.length > 0) {
                existingList.innerHTML = matchingNotes.map(note => {
                    let badge = '';
                    if (note.is_objection) {
                        badge = '<span class="rounded-full px-2 py-0.5 text-xs font-medium bg-yellow-100 text-yellow-700">Objection</span>';
                    } else if (note.category) {
                        badge = `<span class="rounded-full px-2 py-0.5 text-xs font-medium bg-blue-100 text-blue-700">${escapeHtml(note.category.name)}</span>`;
                    }
                    return `<div class="bg-white rounded-lg p-2.5 border border-gray-200">
                        <p class="text-sm text-gray-900">${escapeHtml(note.note_text)}</p>
                        ${badge ? `<div class="mt-1.5">${badge}</div>` : ''}
                    </div>`;
                }).join('');
                existingContainer.classList.remove('hidden');
                document.getElementById('note-form-label').textContent = 'Add Another Note';
            } else {
                existingContainer.classList.add('hidden');
                document.getElementById('note-form-label').textContent = 'Coaching Note';
            }

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

        document.getElementById('is-golden-checkbox').addEventListener('change', function() {
            const helperText = document.getElementById('golden-helper-text');
            if (this.checked) {
                helperText.classList.remove('hidden');
            } else {
                helperText.classList.add('hidden');
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
            const isGolden = document.getElementById('is-golden-checkbox').checked;

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
                        is_exemplar: isGolden,
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

        // ========================================
        // Skip Call Flow (grading page)
        // ========================================
        const gradingSkipToggle = document.getElementById('grading-skip-toggle');
        const gradingSkipPanel = document.getElementById('grading-skip-panel');
        const gradingSkipCancel = document.getElementById('grading-skip-cancel');
        const gradingSkipConfirm = document.getElementById('grading-skip-confirm');
        const gradingSkipRadios = document.querySelectorAll('input[name="grading_skip_reason"]');

        if (gradingSkipToggle) {
            gradingSkipToggle.addEventListener('click', () => {
                gradingSkipPanel.style.display = gradingSkipPanel.style.display === 'none' ? 'block' : 'none';
            });

            gradingSkipCancel.addEventListener('click', () => {
                gradingSkipPanel.style.display = 'none';
            });

            gradingSkipRadios.forEach(r => r.addEventListener('change', () => {
                gradingSkipConfirm.disabled = false;
            }));

            gradingSkipConfirm.addEventListener('click', async () => {
                const reason = document.querySelector('input[name="grading_skip_reason"]:checked')?.value;
                if (!reason) return;

                gradingSkipConfirm.disabled = true;
                gradingSkipConfirm.textContent = 'Skipping...';

                try {
                    const response = await fetch('{{ route("manager.calls.skip", $call) }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify({
                            skip_reason: reason,
                            page_seconds: getPageSeconds(),
                            playback_seconds: Math.floor(state.playbackSeconds),
                        }),
                    });
                    const data = await response.json();
                    if (data.success) {
                        window.location.href = data.redirect;
                    } else {
                        alert(data.message || 'Failed to skip call.');
                        gradingSkipConfirm.disabled = false;
                        gradingSkipConfirm.textContent = 'Confirm Skip';
                    }
                } catch (e) {
                    alert('Network error. Please try again.');
                    gradingSkipConfirm.disabled = false;
                    gradingSkipConfirm.textContent = 'Confirm Skip';
                }
            });
        }
    </script>
</body>
</html>
