<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Account;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    /**
     * Show projects management page
     */
    public function index(Request $request)
    {
        $accounts = Account::where('is_active', true)->orderBy('name')->get();
        $selectedAccountId = $request->get('account_id', $accounts->first()?->id);

        $projects = Project::where('account_id', $selectedAccountId)
            ->withCount('calls')
            ->orderBy('name')
            ->get();

        return view('admin.projects.index', compact('accounts', 'projects', 'selectedAccountId'));
    }

    /**
     * Store a new project
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'account_id' => 'required|exists:accounts,id',
            'name' => 'required|string|max:255',
            'external_id' => 'nullable|string|max:255',
        ]);

        // Check for duplicate name within account
        $exists = Project::where('account_id', $validated['account_id'])
            ->where('name', $validated['name'])
            ->exists();

        if ($exists) {
            return back()->with('error', 'A project with this name already exists for this office.');
        }

        Project::create([
            'account_id' => $validated['account_id'],
            'name' => $validated['name'],
            'external_id' => $validated['external_id'] ?? null,
            'is_active' => true,
        ]);

        return back()->with('success', 'Project added successfully.');
    }

    /**
     * Update a project
     */
    public function update(Request $request, Project $project)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'external_id' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        // Check for duplicate name within account
        $exists = Project::where('account_id', $project->account_id)
            ->where('name', $validated['name'])
            ->where('id', '!=', $project->id)
            ->exists();

        if ($exists) {
            return back()->with('error', 'A project with this name already exists for this office.');
        }

        $project->update([
            'name' => $validated['name'],
            'external_id' => $validated['external_id'] ?? null,
            'is_active' => $request->has('is_active'),
        ]);

        return back()->with('success', 'Project updated successfully.');
    }

    /**
     * Delete a project
     */
    public function destroy(Project $project)
    {
        // Check if project has calls
        if ($project->calls()->exists()) {
            return back()->with('error', 'Cannot delete - this project has associated calls. Deactivate instead.');
        }

        $project->delete();

        return back()->with('success', 'Project deleted successfully.');
    }
}
