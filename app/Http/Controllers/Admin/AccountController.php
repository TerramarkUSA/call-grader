<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Services\CTMService;
use App\Services\CallSyncService;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    /**
     * Show accounts list
     */
    public function index()
    {
        $accounts = Account::withCount('calls')
            ->orderBy('name')
            ->get();

        return view('admin.accounts.index', compact('accounts'));
    }

    /**
     * Show create account form
     */
    public function create()
    {
        return view('admin.accounts.create');
    }

    /**
     * Store new account
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'ctm_account_id' => 'required|string|unique:accounts,ctm_account_id',
            'ctm_api_key' => 'required|string',
            'ctm_api_secret' => 'required|string',
        ]);

        $account = Account::create([
            'name' => $request->name,
            'ctm_account_id' => $request->ctm_account_id,
            'ctm_api_key' => $request->ctm_api_key,
            'ctm_api_secret' => $request->ctm_api_secret,
            'is_active' => true,
        ]);

        // Test the connection
        $ctmService = new CTMService($account);
        $testResult = $ctmService->testConnection();

        if (!$testResult['success']) {
            // Connection failed, but account is created
            return redirect()->route('admin.accounts.index')
                ->with('warning', "Account created but connection test failed: {$testResult['message']}. Please verify credentials.");
        }

        return redirect()->route('admin.accounts.index')
            ->with('success', "Office '{$account->name}' connected successfully!");
    }

    /**
     * Show edit form
     */
    public function edit(Account $account)
    {
        return view('admin.accounts.edit', compact('account'));
    }

    /**
     * Update account
     */
    public function update(Request $request, Account $account)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'ctm_account_id' => 'required|string|unique:accounts,ctm_account_id,' . $account->id,
            'ctm_api_key' => 'nullable|string',
            'ctm_api_secret' => 'nullable|string',
        ]);

        $data = [
            'name' => $request->name,
            'ctm_account_id' => $request->ctm_account_id,
        ];

        // Only update credentials if provided
        if ($request->filled('ctm_api_key')) {
            $data['ctm_api_key'] = $request->ctm_api_key;
        }
        if ($request->filled('ctm_api_secret')) {
            $data['ctm_api_secret'] = $request->ctm_api_secret;
        }

        $account->update($data);

        return redirect()->route('admin.accounts.index')
            ->with('success', "Office '{$account->name}' updated.");
    }

    /**
     * Test CTM connection
     */
    public function testConnection(Account $account)
    {
        $ctmService = new CTMService($account);
        $result = $ctmService->testConnection();

        if ($result['success']) {
            return back()->with('success', "Connection successful! CTM Account: {$result['account_name']}");
        }

        return back()->with('error', "Connection failed: {$result['message']}");
    }

    /**
     * Sync calls from CTM
     */
    public function syncCalls(Account $account)
    {
        $syncService = new CallSyncService($account);
        $stats = $syncService->sync(14); // Last 14 days

        $message = "Sync complete! Fetched: {$stats['total_fetched']}, Created: {$stats['created']}, Updated: {$stats['updated']}";

        if ($stats['errors'] > 0) {
            $message .= ", Errors: {$stats['errors']}";
            return back()->with('warning', $message);
        }

        return back()->with('success', $message);
    }

    /**
     * Toggle account active status
     */
    public function toggleActive(Account $account)
    {
        $account->update(['is_active' => !$account->is_active]);

        $status = $account->is_active ? 'activated' : 'deactivated';
        return back()->with('success', "Office '{$account->name}' has been {$status}.");
    }
}
