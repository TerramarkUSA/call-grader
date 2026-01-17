<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rep;
use App\Models\Account;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RepController extends Controller
{
    public function index(Request $request)
    {
        $query = Rep::with('account:id,name');

        if ($request->filled('account')) {
            $query->where('account_id', $request->account);
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $reps = $query->orderBy('name')->paginate(25)->withQueryString();
        $accounts = Account::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Admin/Reps/Index', [
            'reps' => $reps,
            'accounts' => $accounts,
            'filters' => $request->only(['account', 'search']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'account_id' => 'required|exists:accounts,id',
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'ctm_name' => 'nullable|string|max:255',
        ]);

        Rep::create($validated);

        return back()->with('success', 'Rep added successfully.');
    }

    public function update(Request $request, Rep $rep)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'ctm_name' => 'nullable|string|max:255',
            'is_active' => 'boolean',
        ]);

        $rep->update($validated);

        return back()->with('success', 'Rep updated successfully.');
    }

    public function destroy(Rep $rep)
    {
        $rep->delete();
        return back()->with('success', 'Rep deleted successfully.');
    }
}
