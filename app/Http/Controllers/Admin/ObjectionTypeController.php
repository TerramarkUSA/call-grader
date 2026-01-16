<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ObjectionType;
use Illuminate\Http\Request;

class ObjectionTypeController extends Controller
{
    /**
     * Show objection types page
     */
    public function index()
    {
        $objectionTypes = ObjectionType::withCount('coachingNotes as grades_count')
            ->orderBy('sort_order')
            ->get();

        return view('admin.objection-types.index', compact('objectionTypes'));
    }

    /**
     * Store a new objection type
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:objection_types,name',
        ]);

        $maxSort = ObjectionType::max('sort_order') ?? 0;

        ObjectionType::create([
            'name' => $validated['name'],
            'sort_order' => $maxSort + 1,
            'is_active' => true,
        ]);

        return back()->with('success', 'Objection type added.');
    }

    /**
     * Update an objection type
     */
    public function update(Request $request, ObjectionType $objectionType)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:objection_types,name,' . $objectionType->id,
            'is_active' => 'boolean',
        ]);

        $objectionType->update([
            'name' => $validated['name'],
            'is_active' => $request->has('is_active'),
        ]);

        return back()->with('success', 'Objection type updated.');
    }

    /**
     * Delete an objection type
     */
    public function destroy(ObjectionType $objectionType)
    {
        // Check if in use
        if ($objectionType->coachingNotes()->exists()) {
            return back()->with('error', 'Cannot delete - this type is used in coaching notes. Deactivate it instead.');
        }

        $objectionType->delete();

        return back()->with('success', 'Objection type deleted.');
    }

    /**
     * Reorder objection types
     */
    public function reorder(Request $request)
    {
        $validated = $request->validate([
            'order' => 'required|array',
            'order.*' => 'exists:objection_types,id',
        ]);

        foreach ($validated['order'] as $index => $id) {
            ObjectionType::where('id', $id)->update(['sort_order' => $index + 1]);
        }

        return back()->with('success', 'Order updated.');
    }
}
