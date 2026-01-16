<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RubricCategory;
use App\Models\RubricCheckpoint;
use Illuminate\Http\Request;

class RubricController extends Controller
{
    /**
     * Show rubric categories page
     */
    public function categories()
    {
        $categories = RubricCategory::orderBy('sort_order')->get();

        return view('admin.rubric.categories', compact('categories'));
    }

    /**
     * Update a rubric category
     */
    public function updateCategory(Request $request, RubricCategory $category)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'weight' => 'required|numeric|min:1|max:100',
            'is_active' => 'boolean',
        ]);

        $category->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'weight' => $validated['weight'],
            'is_active' => $request->has('is_active'),
        ]);

        return back()->with('success', 'Category updated successfully.');
    }

    /**
     * Reorder categories
     */
    public function reorderCategories(Request $request)
    {
        $validated = $request->validate([
            'order' => 'required|array',
            'order.*' => 'exists:rubric_categories,id',
        ]);

        foreach ($validated['order'] as $index => $id) {
            RubricCategory::where('id', $id)->update(['sort_order' => $index + 1]);
        }

        return back()->with('success', 'Categories reordered.');
    }

    /**
     * Show rubric checkpoints page
     */
    public function checkpoints()
    {
        $checkpoints = RubricCheckpoint::orderBy('type')
            ->orderBy('sort_order')
            ->get();

        return view('admin.rubric.checkpoints', compact('checkpoints'));
    }

    /**
     * Store a new checkpoint
     */
    public function storeCheckpoint(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:500',
            'type' => 'required|in:positive,negative',
            'description' => 'nullable|string|max:1000',
        ]);

        $maxSort = RubricCheckpoint::where('type', $validated['type'])->max('sort_order') ?? 0;

        RubricCheckpoint::create([
            'name' => $validated['name'],
            'type' => $validated['type'],
            'description' => $validated['description'],
            'sort_order' => $maxSort + 1,
            'is_active' => true,
        ]);

        return back()->with('success', 'Checkpoint added.');
    }

    /**
     * Update a checkpoint
     */
    public function updateCheckpoint(Request $request, RubricCheckpoint $checkpoint)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:500',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
        ]);

        $checkpoint->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_active' => $request->has('is_active'),
        ]);

        return back()->with('success', 'Checkpoint updated.');
    }

    /**
     * Delete a checkpoint
     */
    public function deleteCheckpoint(RubricCheckpoint $checkpoint)
    {
        // Check if checkpoint has responses
        if ($checkpoint->checkpointResponses()->exists()) {
            return back()->with('error', 'Cannot delete - this checkpoint is used in grades. Deactivate it instead.');
        }

        $checkpoint->delete();

        return back()->with('success', 'Checkpoint deleted.');
    }

    /**
     * Reorder checkpoints
     */
    public function reorderCheckpoints(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:positive,negative',
            'order' => 'required|array',
            'order.*' => 'exists:rubric_checkpoints,id',
        ]);

        foreach ($validated['order'] as $index => $id) {
            RubricCheckpoint::where('id', $id)->update(['sort_order' => $index + 1]);
        }

        return back()->with('success', 'Checkpoints reordered.');
    }
}
