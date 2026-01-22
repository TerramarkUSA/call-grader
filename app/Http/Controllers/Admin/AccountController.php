<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Call;
use App\Jobs\EnrichCallFromSalesforce;
use App\Services\CTMService;
use App\Services\CallSyncService;
use App\Services\SalesforceService;
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

        // Add SF stats to each account
        $sfService = new SalesforceService();
        $sfConnected = $sfService->isConnected();

        foreach ($accounts as $account) {
            $account->sf_enriched_count = Call::where('account_id', $account->id)
                ->whereNotNull('sf_chance_id')
                ->count();
            $account->sf_pending_count = Call::where('account_id', $account->id)
                ->whereNull('sf_chance_id')
                ->whereNotNull('ctm_activity_id')
                ->count();
        }

        return view('admin.accounts.index', compact('accounts', 'sfConnected'));
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

    /**
     * Sync calls with Salesforce for an account
     */
    public function syncSalesforce(Account $account)
    {
        $sfService = new SalesforceService();

        if (!$sfService->isConnected()) {
            return back()->with('error', 'Salesforce is not connected. Please configure in Settings.');
        }

        $calls = Call::where('account_id', $account->id)
            ->whereNull('sf_chance_id')
            ->whereNotNull('ctm_activity_id')
            ->get();

        if ($calls->isEmpty()) {
            return back()->with('info', "No pending calls to enrich for {$account->name}.");
        }

        foreach ($calls as $call) {
            EnrichCallFromSalesforce::dispatch($call);
        }

        return back()->with('success', "Queued {$calls->count()} calls for Salesforce enrichment.");
    }

    /**
     * Save SF office mapping for all accounts
     */
    public function saveOfficeMappings(Request $request)
    {
        $validated = $request->validate([
            'mappings' => 'required|array',
            'mappings.*.account_id' => 'required|exists:accounts,id',
            'mappings.*.sf_office_name' => 'nullable|string|max:255',
        ]);

        foreach ($validated['mappings'] as $mapping) {
            Account::where('id', $mapping['account_id'])
                ->update(['sf_office_name' => $mapping['sf_office_name'] ?: null]);
        }

        return back()->with('success', 'Office mappings saved.');
    }
}
