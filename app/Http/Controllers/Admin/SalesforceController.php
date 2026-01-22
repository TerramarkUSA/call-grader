<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rep;
use App\Models\Project;
use App\Models\Setting;
use App\Services\SalesforceService;
use Illuminate\Http\Request;

class SalesforceController extends Controller
{
    public function index()
    {
        $service = new SalesforceService();

        // Global connection status
        $sfConnected = $service->isConnected();
        $sfInstanceUrl = Setting::get('sf_instance_url');
        $sfClientId = Setting::get('sf_client_id');
        $sfConnectedAt = Setting::get('sf_connected_at');

        // Field mapping
        $fieldMapping = $service->getFieldMapping();

        // Reps and projects for legacy mapping
        $reps = Rep::with('account:id,name')
            ->where('is_active', true)
            ->get(['id', 'name', 'email', 'account_id', 'sf_user_id']);

        $projects = Project::with('account:id,name')
            ->where('is_active', true)
            ->get(['id', 'name', 'account_id', 'sf_project_name']);

        return view('admin.settings.salesforce', compact(
            'sfConnected',
            'sfInstanceUrl',
            'sfClientId',
            'sfConnectedAt',
            'fieldMapping',
            'reps',
            'projects'
        ));
    }

    public function saveCredentials(Request $request)
    {
        $validated = $request->validate([
            'sf_instance_url' => 'required|url',
            'sf_client_id' => 'required|string',
            'sf_client_secret' => 'required|string',
        ]);

        Setting::set('sf_instance_url', rtrim($validated['sf_instance_url'], '/'));
        Setting::set('sf_client_id', $validated['sf_client_id']);
        Setting::setEncrypted('sf_client_secret', $validated['sf_client_secret']);

        // Automatically redirect to OAuth flow after saving
        $service = new SalesforceService();
        $redirectUri = route('admin.salesforce.callback');

        return redirect($service->getAuthorizationUrl($redirectUri));
    }

    public function connect()
    {
        $instanceUrl = Setting::get('sf_instance_url');
        $clientId = Setting::get('sf_client_id');

        if (!$instanceUrl || !$clientId) {
            return back()->with('error', 'Please save credentials first.');
        }

        $service = new SalesforceService();
        $redirectUri = route('admin.salesforce.callback');

        return redirect($service->getAuthorizationUrl($redirectUri));
    }

    public function callback(Request $request)
    {
        if ($request->has('error')) {
            return redirect()->route('admin.salesforce.index')
                ->with('error', 'Salesforce authorization failed: ' . $request->get('error_description'));
        }

        $service = new SalesforceService();
        $redirectUri = route('admin.salesforce.callback');

        if ($service->handleCallback($request->get('code'), $redirectUri)) {
            return redirect()->route('admin.salesforce.index')
                ->with('success', 'Salesforce connected successfully.');
        }

        return redirect()->route('admin.salesforce.index')
            ->with('error', 'Failed to connect to Salesforce.');
    }

    public function disconnect()
    {
        $service = new SalesforceService();
        $service->disconnect();

        return back()->with('success', 'Salesforce disconnected.');
    }

    public function testConnection()
    {
        $service = new SalesforceService();

        if (!$service->isConnected()) {
            return response()->json(['success' => false, 'message' => 'Not connected']);
        }

        $result = $service->query('SELECT Id FROM User LIMIT 1');

        if ($result === null) {
            return response()->json(['success' => false, 'message' => 'Query failed']);
        }

        return response()->json(['success' => true, 'message' => 'Connection successful']);
    }

    public function saveFieldMapping(Request $request)
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
            'office_field' => 'nullable|string',
        ]);

        $service = new SalesforceService();
        $service->setFieldMapping($validated);

        return back()->with('success', 'Field mapping saved.');
    }

    public function getUsers()
    {
        $service = new SalesforceService();

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

    public function autoMatchReps()
    {
        $service = new SalesforceService();

        if (!$service->isConnected()) {
            return back()->with('error', 'Salesforce not connected.');
        }

        $sfUsers = $service->getUsers();
        $matched = 0;
        $reps = Rep::whereNotNull('email')
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
