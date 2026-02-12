<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Mail\CallFeedbackMail;
use App\Models\Call;
use App\Models\Grade;
use App\Models\GradeCategoryScore;
use App\Models\GradeCheckpointResponse;
use App\Models\ObjectionType;
use App\Models\Project;
use App\Models\Rep;
use App\Models\RubricCategory;
use App\Models\RubricCheckpoint;
use App\Services\ScoringService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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
        $this->authorize('view', $call);

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

        // Get reps and projects for the account
        $reps = Rep::where('account_id', $call->account_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $projects = Project::where('account_id', $call->account_id)
            ->where('is_active', true)
            ->orderBy('name')
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
            'speakersSwapped',
            'reps',
            'projects'
        ));
    }

    /**
     * Save or update grade (draft or submitted)
     */
    public function store(Request $request, Call $call)
    {
        $this->authorize('grade', $call);

        $validated = $request->validate([
            'category_scores' => 'required|array',
            'category_scores.*' => 'nullable|integer|min:1|max:4',
            'checkpoint_responses' => 'nullable|array',
            'checkpoint_responses.*' => 'nullable|boolean',
            'appointment_quality' => 'nullable|in:solid,tentative,backed_in',
            'rep_id' => 'nullable|exists:reps,id',
            'project_id' => 'nullable|exists:projects,id',
            'outcome' => 'nullable|in:appointment_set,no_appointment,callback,not_qualified,other',
            'playback_seconds' => 'required|integer|min:0',
            'status' => 'required|in:draft,submitted',
        ]);

        // Update call record with rep, project, outcome
        $call->update([
            'rep_id' => $validated['rep_id'] ?? null,
            'project_id' => $validated['project_id'] ?? null,
            'outcome' => $validated['outcome'] ?? null,
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

            // Check if grade already exists
            $existingGrade = Grade::where('call_id', $call->id)
                ->where('graded_by', Auth::id())
                ->first();

            // Build update data
            $gradeData = [
                'status' => $validated['status'],
                'overall_score' => $overallScore,
                'appointment_quality' => $validated['appointment_quality'],
                'playback_seconds' => $validated['playback_seconds'],
                'grading_completed_at' => $validated['status'] === 'submitted' ? now() : null,
            ];

            // Only set grading_started_at for new grades
            if (!$existingGrade) {
                $gradeData['grading_started_at'] = now();
            }

            // Find or create grade
            $grade = Grade::updateOrCreate(
                [
                    'call_id' => $call->id,
                    'graded_by' => Auth::id(),
                ],
                $gradeData
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
        $this->authorize('view', $call);

        if (!$call->recording_path) {
            abort(404, 'Recording not found');
        }

        // Check if it's a URL or local path
        if (filter_var($call->recording_path, FILTER_VALIDATE_URL)) {
            // Proxy the external audio through our server to avoid leaking the URL
            try {
                $response = Http::timeout(30)->get($call->recording_path);

                if (!$response->successful()) {
                    abort(404, 'Recording not available');
                }

                $contentType = $response->header('Content-Type') ?? 'audio/mpeg';

                return response($response->body(), 200, [
                    'Content-Type' => $contentType,
                    'Accept-Ranges' => 'bytes',
                    'Cache-Control' => 'private, max-age=3600',
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to proxy recording', [
                    'call_id' => $call->id,
                    'error' => $e->getMessage(),
                ]);
                abort(404, 'Recording not available');
            }
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
        $this->authorize('grade', $call);

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
        $this->authorize('update', $call);

        $call->update([
            'speakers_swapped' => !$call->speakers_swapped,
        ]);

        return response()->json([
            'success' => true,
            'speakers_swapped' => $call->speakers_swapped,
        ]);
    }

    /**
     * Get sharing information for a call's grade
     */
    public function getSharingInfo(Call $call)
    {
        $this->authorize('view', $call);

        $call->load(['rep', 'project']);

        // Check if call has a rep
        if (!$call->rep) {
            return response()->json([
                'error' => 'No rep assigned to this call.',
            ], 400);
        }

        // Check if rep has an email
        if (!$call->rep->email) {
            return response()->json([
                'error' => 'Rep does not have an email address.',
            ], 400);
        }

        // Get the submitted grade for this call
        $grade = Grade::where('call_id', $call->id)
            ->where('status', 'submitted')
            ->with(['coachingNotes', 'sharedBy'])
            ->first();

        if (!$grade) {
            return response()->json([
                'error' => 'No submitted grade found for this call.',
            ], 400);
        }

        // Count coaching notes (only those with transcript snippets)
        $noteCount = $grade->coachingNotes->filter(fn($note) => $note->line_index_start !== null)->count();

        return response()->json([
            'rep_name' => $call->rep->name,
            'rep_email' => $call->rep->email,
            'has_notes' => $noteCount > 0,
            'note_count' => $noteCount,
            'was_shared' => $grade->wasShared(),
            'shared_at' => $grade->shared_with_rep_at?->format('M j, Y g:i A'),
            'shared_by' => $grade->sharedBy?->name,
        ]);
    }

    /**
     * Share call feedback with rep via email
     */
    public function shareWithRep(Request $request, Call $call)
    {
        $this->authorize('view', $call);

        $call->load(['rep', 'project']);

        // Validate rep exists and has email
        if (!$call->rep) {
            return response()->json([
                'error' => 'No rep assigned to this call.',
            ], 400);
        }

        if (!$call->rep->email) {
            return response()->json([
                'error' => 'Rep does not have an email address.',
            ], 400);
        }

        // Get the submitted grade
        $grade = Grade::where('call_id', $call->id)
            ->where('status', 'submitted')
            ->first();

        if (!$grade) {
            return response()->json([
                'error' => 'No submitted grade found for this call.',
            ], 400);
        }

        $manager = Auth::user();
        $repName = $call->rep->name;
        $repEmail = $call->rep->email;

        // Send the email
        Mail::to($repEmail)->send(new CallFeedbackMail(
            $grade,
            $call,
            $manager,
            $repName,
            $repEmail
        ));

        // Update grade with sharing info
        $grade->update([
            'shared_with_rep_at' => now(),
            'shared_with_rep_email' => $repEmail,
            'shared_by_user_id' => $manager->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Feedback sent successfully.',
            'shared_at' => $grade->fresh()->shared_with_rep_at->format('M j, Y g:i A'),
        ]);
    }
}
