<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rep;
use App\Models\Account;
use Illuminate\Http\Request;

class RepController extends Controller
{
    /**
     * Show reps management page
     */
    public function index(Request $request)
    {
        $accounts = Account::where('is_active', true)->orderBy('name')->get();
        $selectedAccountId = $request->get('account_id', $accounts->first()?->id);

        $reps = Rep::where('account_id', $selectedAccountId)
            ->withCount('calls')
            ->orderBy('name')
            ->get();

        return view('admin.reps.index', compact('accounts', 'reps', 'selectedAccountId'));
    }

    /**
     * Store a new rep
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'account_id' => 'required|exists:accounts,id',
            'name' => 'required|string|max:255',
            'external_id' => 'nullable|string|max:255',
        ]);

        // Check for duplicate name within account
        $exists = Rep::where('account_id', $validated['account_id'])
            ->where('name', $validated['name'])
            ->exists();

        if ($exists) {
            return back()->with('error', 'A rep with this name already exists for this office.');
        }

        Rep::create([
            'account_id' => $validated['account_id'],
            'name' => $validated['name'],
            'external_id' => $validated['external_id'] ?? null,
            'is_active' => true,
        ]);

        return back()->with('success', 'Rep added successfully.');
    }

    /**
     * Update a rep
     */
    public function update(Request $request, Rep $rep)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'external_id' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        // Check for duplicate name within account
        $exists = Rep::where('account_id', $rep->account_id)
            ->where('name', $validated['name'])
            ->where('id', '!=', $rep->id)
            ->exists();

        if ($exists) {
            return back()->with('error', 'A rep with this name already exists for this office.');
        }

        $rep->update([
            'name' => $validated['name'],
            'external_id' => $validated['external_id'] ?? null,
            'is_active' => $request->has('is_active'),
        ]);

        return back()->with('success', 'Rep updated successfully.');
    }

    /**
     * Delete a rep
     */
    public function destroy(Rep $rep)
    {
        // Check if rep has calls
        if ($rep->calls()->exists()) {
            return back()->with('error', 'Cannot delete - this rep has associated calls. Deactivate instead.');
        }

        $rep->delete();

        return back()->with('success', 'Rep deleted successfully.');
    }
}
