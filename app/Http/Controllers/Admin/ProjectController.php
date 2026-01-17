<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Account;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $query = Project::with('account:id,name');

        if ($request->filled('account')) {
            $query->where('account_id', $request->account);
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $projects = $query->orderBy('name')->paginate(25)->withQueryString();
        $accounts = Account::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Admin/Projects/Index', [
            'projects' => $projects,
            'accounts' => $accounts,
            'filters' => $request->only(['account', 'search']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'account_id' => 'required|exists:accounts,id',
            'name' => 'required|string|max:255',
            'ctm_name' => 'nullable|string|max:255',
        ]);

        Project::create($validated);

        return back()->with('success', 'Project added successfully.');
    }

    public function update(Request $request, Project $project)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'ctm_name' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $project->update($validated);

        return back()->with('success', 'Project updated successfully.');
    }

    public function destroy(Project $project)
    {
        $project->delete();
        return back()->with('success', 'Project deleted successfully.');
    }
}
