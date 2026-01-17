<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Rep;
use App\Models\Project;
use App\Services\SalesforceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class SalesforceController extends Controller
{
    public function index()
    {
        $accounts = Account::where('is_active', true)
            ->get()
            ->map(fn($a) => [
                'id' => $a->id,
                'name' => $a->name,
                'sf_instance_url' => $a->sf_instance_url,
                'sf_client_id' => $a->sf_client_id,
                'sf_connected' => $a->sf_connected_at !== null,
                'sf_connected_at' => $a->sf_connected_at,
                'sf_field_mapping' => $a->sf_field_mapping ?? [],
            ]);

        $reps = Rep::with('account:id,name')
            ->where('is_active', true)
            ->get(['id', 'name', 'email', 'account_id', 'sf_user_id']);

        $projects = Project::with('account:id,name')
            ->where('is_active', true)
            ->get(['id', 'name', 'account_id', 'sf_project_name']);

        // Default field mapping for display
        $defaultFieldMapping = [
            'chance_object' => 'Chance__c',
            'ctm_call_id_field' => 'CTM_Call_ID__c',
            'project_field' => 'Project__c',
            'land_sale_field' => 'Land_Sale__c',
            'contact_status_field' => 'Contact_Status__c',
            'appointment_made_field' => 'Appointment_Made__c',
            'toured_property_field' => 'Toured_Property__c',
            'opportunity_created_field' => 'Opportunity_Created__c',
        ];

        return view('admin.settings.salesforce', compact(
            'accounts',
            'reps',
            'projects',
            'defaultFieldMapping'
        ));
    }

    public function saveCredentials(Request $request, Account $account)
    {
        $validated = $request->validate([
            'sf_instance_url' => 'required|url',
            'sf_client_id' => 'required|string',
            'sf_client_secret' => 'required|string',
        ]);

        $account->update([
            'sf_instance_url' => rtrim($validated['sf_instance_url'], '/'),
            'sf_client_id' => $validated['sf_client_id'],
            'sf_client_secret' => Crypt::encryptString($validated['sf_client_secret']),
        ]);

        return back()->with('success', 'Salesforce credentials saved.');
    }

    public function connect(Account $account)
    {
        if (!$account->sf_instance_url || !$account->sf_client_id) {
            return back()->with('error', 'Please save credentials first.');
        }

        $service = new SalesforceService($account);
        $redirectUri = route('admin.salesforce.callback', ['account' => $account->id]);

        return redirect($service->getAuthorizationUrl($redirectUri));
    }

    public function callback(Request $request, Account $account)
    {
        if ($request->has('error')) {
            return redirect()->route('admin.salesforce.index')
                ->with('error', 'Salesforce authorization failed: ' . $request->get('error_description'));
        }

        $service = new SalesforceService($account);
        $redirectUri = route('admin.salesforce.callback', ['account' => $account->id]);

        if ($service->handleCallback($request->get('code'), $redirectUri)) {
            return redirect()->route('admin.salesforce.index')
                ->with('success', 'Salesforce connected successfully.');
        }

        return redirect()->route('admin.salesforce.index')
            ->with('error', 'Failed to connect to Salesforce.');
    }

    public function disconnect(Account $account)
    {
        $service = new SalesforceService($account);
        $service->disconnect();

        return back()->with('success', 'Salesforce disconnected.');
    }

    public function testConnection(Account $account)
    {
        $service = new SalesforceService($account);

        if (!$service->isConnected()) {
            return response()->json(['success' => false, 'message' => 'Not connected']);
        }

        $result = $service->query('SELECT Id FROM User LIMIT 1');

        if ($result === null) {
            return response()->json(['success' => false, 'message' => 'Query failed']);
        }

        return response()->json(['success' => true, 'message' => 'Connection successful']);
    }

    public function saveFieldMapping(Request $request, Account $account)
    {
        $validated = $request->validate([
            'chance_object' => 'required|string',
            'ctm_call_id_field' => 'required|string',
            'project_field' => 'required|string',
            'land_sale_field' => 'required|string',
            'contact_status_field' => 'nullable|string',
            'appointment_made_field' => 'required|string',
            'toured_property_field' => 'required|string',
            'opportunity_created_field' => 'required|string',
        ]);

        $account->update(['sf_field_mapping' => $validated]);

        return back()->with('success', 'Field mapping saved.');
    }

    public function getUsers(Account $account)
    {
        $service = new SalesforceService($account);

        if (!$service->isConnected()) {
            return response()->json([]);
        }

        return response()->json($service->getUsers());
    }

    public function saveRepMapping(Request $request)
    {
        $validated = $request->validate([
            'mappings' => 'required|array',
            'mappings.*.rep_id' => 'required|exists:reps,id',
            'mappings.*.sf_user_id' => 'nullable|string|max:18',
        ]);

        foreach ($validated['mappings'] as $mapping) {
            Rep::where('id', $mapping['rep_id'])
                ->update(['sf_user_id' => $mapping['sf_user_id'] ?: null]);
        }

        return back()->with('success', 'Rep mapping saved.');
    }

    public function autoMatchReps(Account $account)
    {
        $service = new SalesforceService($account);

        if (!$service->isConnected()) {
            return back()->with('error', 'Salesforce not connected.');
        }

        $sfUsers = $service->getUsers();
        $matched = 0;
        $reps = Rep::where('account_id', $account->id)
            ->whereNotNull('email')
            ->whereNull('sf_user_id')
            ->get();

        foreach ($reps as $rep) {
            foreach ($sfUsers as $user) {
                if (strtolower($rep->email) === strtolower($user['Email'] ?? '')) {
                    $rep->update(['sf_user_id' => $user['Id']]);
                    $matched++;
                    break;
                }
            }
        }

        return back()->with('success', "Matched {$matched} reps by email.");
    }

    public function saveProjectMapping(Request $request)
    {
        $validated = $request->validate([
            'mappings' => 'required|array',
            'mappings.*.project_id' => 'required|exists:projects,id',
            'mappings.*.sf_project_name' => 'nullable|string|max:255',
        ]);

        foreach ($validated['mappings'] as $mapping) {
            Project::where('id', $mapping['project_id'])
                ->update(['sf_project_name' => $mapping['sf_project_name'] ?: null]);
        }

        return back()->with('success', 'Project mapping saved.');
    }
}
