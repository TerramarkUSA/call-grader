<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Call;
use App\Models\Grade;
use App\Models\GradeCategoryScore;
use App\Models\GradeCheckpointResponse;
use App\Models\ObjectionType;
use App\Models\RubricCategory;
use App\Models\RubricCheckpoint;
use App\Services\ScoringService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GradingController extends Controller
{
    public function __construct(
        protected ScoringService $scoringService
    ) {}

    /**
     * Show grading page for a call
     */
    public function show(Call $call)
    {
        // Ensure call has transcript
        if (!$call->transcript) {
            return redirect()->route('manager.calls.process', $call)
                ->with('error', 'Call must be transcribed before grading.');
        }

        // Load relationships
        $call->load(['rep', 'project', 'account']);

        // Get active rubric categories and checkpoints
        $categories = RubricCategory::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $positiveCheckpoints = RubricCheckpoint::where('is_active', true)
            ->where('type', 'positive')
            ->orderBy('sort_order')
            ->get();

        $negativeCheckpoints = RubricCheckpoint::where('is_active', true)
            ->where('type', 'negative')
            ->orderBy('sort_order')
            ->get();

        // Check for existing grade by this manager
        $existingGrade = Grade::where('call_id', $call->id)
            ->where('graded_by', Auth::id())
            ->with(['categoryScores', 'checkpointResponses'])
            ->first();

        // Transcript is automatically decoded via model cast
        // Extract utterances array from transcript data
        $transcriptData = $call->transcript ?? [];
        $transcript = $transcriptData['utterances'] ?? [];
        $isMultichannel = $transcriptData['multichannel'] ?? false;
        $speakersSwapped = (bool) $call->speakers_swapped;

        // Get objection types for notes and "Why No Appointment" modal
        $objectionTypes = ObjectionType::where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name']);

        return view('manager.grading.show', compact(
            'call',
            'categories',
            'positiveCheckpoints',
            'negativeCheckpoints',
            'existingGrade',
            'transcript',
            'objectionTypes',
            'isMultichannel',
            'speakersSwapped'
        ));
    }

    /**
     * Save or update grade (draft or submitted)
     */
    public function store(Request $request, Call $call)
    {
        $validated = $request->validate([
            'category_scores' => 'required|array',
            'category_scores.*' => 'nullable|integer|min:1|max:4',
            'checkpoint_responses' => 'nullable|array',
            'checkpoint_responses.*' => 'nullable|boolean',
            'appointment_quality' => 'nullable|in:solid,tentative,backed_in',
            'playback_seconds' => 'required|integer|min:0',
            'status' => 'required|in:draft,submitted',
        ]);

        $grade = DB::transaction(function () use ($validated, $call) {
            // Calculate weighted score
            $scoreData = $this->scoringService->calculateWeightedScore(
                array_filter($validated['category_scores'], fn($v) => $v !== null)
            );

            // Convert percentage to 1-4 scale for overall_score
            // The percentage is 0-100, so we map it to 1-4
            $overallScore = $scoreData['percentage'] > 0
                ? round(($scoreData['percentage'] / 100) * 4, 2)
                : null;

            // Find or create grade
            $grade = Grade::updateOrCreate(
                [
                    'call_id' => $call->id,
                    'graded_by' => Auth::id(),
                ],
                [
                    'status' => $validated['status'],
                    'overall_score' => $overallScore,
                    'appointment_quality' => $validated['appointment_quality'],
                    'playback_seconds' => $validated['playback_seconds'],
                    'grading_started_at' => DB::raw('COALESCE(grading_started_at, NOW())'),
                    'grading_completed_at' => $validated['status'] === 'submitted' ? now() : null,
                ]
            );

            // Save category scores
            foreach ($validated['category_scores'] as $categoryId => $score) {
                if ($score !== null) {
                    GradeCategoryScore::updateOrCreate(
                        [
                            'grade_id' => $grade->id,
                            'rubric_category_id' => $categoryId,
                        ],
                        ['score' => $score]
                    );
                } else {
                    // Remove score if null
                    GradeCategoryScore::where('grade_id', $grade->id)
                        ->where('rubric_category_id', $categoryId)
                        ->delete();
                }
            }

            // Save checkpoint responses
            if (!empty($validated['checkpoint_responses'])) {
                foreach ($validated['checkpoint_responses'] as $checkpointId => $observed) {
                    if ($observed !== null) {
                        GradeCheckpointResponse::updateOrCreate(
                            [
                                'grade_id' => $grade->id,
                                'rubric_checkpoint_id' => $checkpointId,
                            ],
                            ['observed' => (bool) $observed]
                        );
                    } else {
                        // Remove response if null
                        GradeCheckpointResponse::where('grade_id', $grade->id)
                            ->where('rubric_checkpoint_id', $checkpointId)
                            ->delete();
                    }
                }
            }

            // Mark call as processed if submitted
            if ($validated['status'] === 'submitted') {
                $call->update([
                    'processed_at' => now(),
                    'call_quality' => 'graded',
                ]);
            }

            return $grade;
        });

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $validated['status'] === 'submitted'
                    ? 'Grade submitted successfully.'
                    : 'Draft saved.',
                'redirect' => $validated['status'] === 'submitted'
                    ? route('manager.calls.index')
                    : null,
            ]);
        }

        $message = $validated['status'] === 'submitted'
            ? 'Grade submitted successfully.'
            : 'Draft saved.';

        if ($validated['status'] === 'submitted') {
            return redirect()->route('manager.calls.index')
                ->with('success', $message);
        }

        return back()->with('success', $message);
    }

    /**
     * Stream audio file for playback
     */
    public function audio(Call $call)
    {
        if (!$call->recording_path) {
            abort(404, 'Recording not found');
        }

        // Check if it's a URL or local path
        if (filter_var($call->recording_path, FILTER_VALIDATE_URL)) {
            // Redirect to external URL
            return redirect($call->recording_path);
        }

        // Use Storage facade to get the correct path (respects disk configuration)
        if (!Storage::exists($call->recording_path)) {
            abort(404, 'Recording file not found');
        }

        $path = Storage::path($call->recording_path);

        $mimeType = 'audio/mpeg';
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        if ($extension === 'wav') {
            $mimeType = 'audio/wav';
        } elseif ($extension === 'mp3') {
            $mimeType = 'audio/mpeg';
        }

        return response()->file($path, [
            'Content-Type' => $mimeType,
            'Accept-Ranges' => 'bytes',
        ]);
    }

    /**
     * Save "Why No Appointment" data
     */
    public function saveNoAppointmentReason(Request $request, Call $call)
    {
        $validated = $request->validate([
            'objection_type_ids' => 'required|array|min:1',
            'objection_type_ids.*' => 'exists:objection_types,id',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Find or create the grade
        $grade = Grade::firstOrCreate(
            [
                'call_id' => $call->id,
                'graded_by' => Auth::id(),
            ],
            [
                'status' => 'draft',
                'overall_score' => 0,
            ]
        );

        // Store as JSON on the grade
        $grade->update([
            'no_appointment_reasons' => [
                'objection_type_ids' => $validated['objection_type_ids'],
                'notes' => $validated['notes'],
            ],
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Toggle speakers swapped for multichannel transcripts
     */
    public function swapSpeakers(Call $call)
    {
        $call->update([
            'speakers_swapped' => !$call->speakers_swapped,
        ]);

        return response()->json([
            'success' => true,
            'speakers_swapped' => $call->speakers_swapped,
        ]);
    }
}
