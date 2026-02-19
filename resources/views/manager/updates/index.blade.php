<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>What's New - Call Grader</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#f9fafb] min-h-screen">
    @include('manager.partials.nav')

    <div class="max-w-3xl mx-auto px-8 py-6">
        <div class="mb-8">
            <h1 class="text-xl font-semibold text-gray-900">What's New</h1>
            <p class="text-sm text-gray-500">Recent updates and new features</p>
        </div>

        <div class="space-y-6">

            {{-- ============================================ --}}
            {{-- ADD NEW ENTRIES AT THE TOP                    --}}
            {{-- Update LATEST_UPDATE in UpdatesController     --}}
            {{-- ============================================ --}}

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <div class="flex items-center gap-3 mb-2">
                    <span class="text-xs font-medium text-white bg-amber-500 rounded-full px-2.5 py-0.5">New</span>
                    <span class="text-xs text-gray-400">January 31, 2026</span>
                </div>
                <h3 class="font-semibold text-gray-900 mb-1">⭐ Golden Moments</h3>
                <p class="text-sm text-gray-600">
                    Mark any coaching note as a "Golden Moment" to share it with your entire office.
                    When adding a note on a transcript snippet, check the Golden Moment box to flag it as an exemplar.
                    Golden moments appear in the new <strong>Golden</strong> tab in the nav — visible to all managers in your office.
                    Great for flagging calls worth replaying in training sessions.
                </p>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <div class="flex items-center gap-3 mb-2">
                    <span class="text-xs text-gray-400">January 31, 2026</span>
                </div>
                <h3 class="font-semibold text-gray-900 mb-1">Notes Tab on Grading Page</h3>
                <p class="text-sm text-gray-600">
                    Click the <strong>Notes</strong> tab while grading to see all your coaching notes for that call in one place.
                    Click any snippet note to jump the audio player to that exact moment and highlight the transcript line.
                    When clicking a transcript line that already has notes, existing notes show above the form.
                </p>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <div class="flex items-center gap-3 mb-2">
                    <span class="text-xs text-gray-400">January 28, 2026</span>
                </div>
                <h3 class="font-semibold text-gray-900 mb-1">Skip Call with Reasons</h3>
                <p class="text-sm text-gray-600">
                    Instead of "Mark as Bad Call," use the <strong>Skip — Not Worth Grading</strong> button on the transcription or grading page.
                    Select a reason from the picklist (too short, wrong call type, voicemail, etc.).
                    Skipped calls are tracked on the admin leaderboard for accountability.
                </p>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <div class="flex items-center gap-3 mb-2">
                    <span class="text-xs text-gray-400">January 27, 2026</span>
                </div>
                <h3 class="font-semibold text-gray-900 mb-1">Call Queue Preview</h3>
                <p class="text-sm text-gray-600">
                    Short calls (under 60 seconds talk time) now open a <strong>Preview</strong> modal instead of going straight to transcription.
                    Listen to the audio first, then decide to skip or grade. Long calls auto-transcribe with one click.
                    Already-transcribed calls show a direct "Grade" button.
                </p>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <div class="flex items-center gap-3 mb-2">
                    <span class="text-xs text-gray-400">January 25, 2026</span>
                </div>
                <h3 class="font-semibold text-gray-900 mb-1">Audio Preview on Transcription Page</h3>
                <p class="text-sm text-gray-600">
                    Before transcribing a call, you can now listen to the audio first — for free, no transcription cost.
                    Click <strong>Click to Load Audio</strong> to stream the recording and decide if the call is worth grading.
                </p>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <div class="flex items-center gap-3 mb-2">
                    <span class="text-xs text-gray-400">January 24, 2026</span>
                </div>
                <h3 class="font-semibold text-gray-900 mb-1">Smooth Transcript Auto-Scroll</h3>
                <p class="text-sm text-gray-600">
                    The transcript now follows audio playback smoothly — like a teleprompter.
                    If you manually scroll to re-read something, auto-scroll pauses and re-engages automatically after 2 seconds.
                </p>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
                <div class="flex items-center gap-3 mb-2">
                    <span class="text-xs text-gray-400">January 22, 2026</span>
                </div>
                <h3 class="font-semibold text-gray-900 mb-1">Feedback Button</h3>
                <p class="text-sm text-gray-600">
                    See the red <strong>Feedback</strong> button in the top nav? Click it to send a message directly to IT.
                    Bug reports, feature requests, questions — anything goes.
                </p>
            </div>

        </div>
    </div>
</body>
</html>
