<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Call;
use App\Models\CoachingNote;
use App\Models\Grade;
use App\Models\ObjectionType;
use App\Models\RubricCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CoachingNoteController extends Controller
{
    /**
     * Get all notes for a call by current manager
     */
    public function index(Call $call)
    {
        $this->authorize('view', $call);

        $notes = CoachingNote::where('call_id', $call->id)
            ->where('author_id', Auth::id())
            ->with(['category:id,name', 'objectionType:id,name'])
            ->orderByRaw('line_index_start IS NULL, line_index_start ASC')
            ->get();

        return response()->json($notes);
    }

    /**
     * Store a new coaching note
     */
    public function store(Request $request, Call $call)
    {
        $this->authorize('view', $call);

        $validated = $request->validate([
            'grade_id' => 'nullable|exists:grades,id',
            'line_index_start' => 'nullable|integer|min:0',
            'line_index_end' => 'nullable|integer|min:0',
            'timestamp_start' => 'nullable|numeric|min:0',
            'timestamp_end' => 'nullable|numeric|min:0',
            'transcript_text' => 'nullable|string|max:2000',
            'note_text' => 'required|string|max:2000',
            'rubric_category_id' => 'nullable|exists:rubric_categories,id',
            'is_objection' => 'boolean',
            'objection_type_id' => 'nullable|required_if:is_objection,true|exists:objection_types,id',
            'objection_outcome' => 'nullable|required_if:is_objection,true|in:overcame,failed',
        ]);

        // Find or get the current grade for this call by this manager
        $grade = null;
        if (!empty($validated['grade_id'])) {
            $grade = Grade::find($validated['grade_id']);
        } else {
            $grade = Grade::where('call_id', $call->id)
                ->where('graded_by', Auth::id())
                ->first();
        }

        // For overall notes (no line_index_start), check if one already exists
        if ($validated['line_index_start'] === null) {
            $existingOverallNote = CoachingNote::where('call_id', $call->id)
                ->where('author_id', Auth::id())
                ->whereNull('line_index_start')
                ->first();
            
            if ($existingOverallNote) {
                // Update existing overall note instead of creating new one
                $existingOverallNote->update([
                    'note_text' => $validated['note_text'],
                    'grade_id' => $grade?->id,
                ]);
                $existingOverallNote->load(['category:id,name', 'objectionType:id,name']);
                return response()->json($existingOverallNote);
            }
        }

        $note = CoachingNote::create([
            'call_id' => $call->id,
            'grade_id' => $grade?->id,
            'author_id' => Auth::id(),
            'line_index_start' => $validated['line_index_start'] ?? null,
            'line_index_end' => $validated['line_index_end'] ?? $validated['line_index_start'] ?? null,
            'timestamp_start' => $validated['timestamp_start'] ?? null,
            'timestamp_end' => $validated['timestamp_end'] ?? $validated['timestamp_start'] ?? null,
            'transcript_text' => $validated['transcript_text'] ?? null,
            'note_text' => $validated['note_text'],
            'rubric_category_id' => $validated['rubric_category_id'] ?? null,
            'is_objection' => $validated['is_objection'] ?? false,
            'objection_type_id' => $validated['objection_type_id'] ?? null,
            'objection_outcome' => $validated['objection_outcome'] ?? null,
        ]);

        $note->load(['category:id,name', 'objectionType:id,name']);

        return response()->json($note, 201);
    }

    /**
     * Update a coaching note
     */
    public function update(Request $request, CoachingNote $note)
    {
        // Ensure user owns this note
        if ($note->author_id !== Auth::id()) {
            abort(403, 'You can only edit your own notes.');
        }

        $validated = $request->validate([
            'note_text' => 'sometimes|required|string|max:2000',
            'rubric_category_id' => 'nullable|exists:rubric_categories,id',
            'is_objection' => 'boolean',
            'objection_type_id' => 'nullable|exists:objection_types,id',
            'objection_outcome' => 'nullable|in:overcame,failed',
        ]);

        $note->update($validated);
        $note->load(['category:id,name', 'objectionType:id,name']);

        return response()->json($note);
    }

    /**
     * Delete a coaching note
     */
    public function destroy(CoachingNote $note)
    {
        if ($note->author_id !== Auth::id()) {
            abort(403, 'You can only delete your own notes.');
        }

        $note->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Get form data (categories and objection types)
     */
    public function formData()
    {
        return response()->json([
            'categories' => RubricCategory::where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'name']),
            'objectionTypes' => ObjectionType::where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'name']),
        ]);
    }
}
