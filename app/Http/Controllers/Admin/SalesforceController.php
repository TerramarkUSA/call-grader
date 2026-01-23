<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
        $sfLastSyncAt = Setting::get('sf_last_sync_at');

        // Field mapping
        $fieldMapping = $service->getFieldMapping();

        // Get objects and fields if connected
        $objects = [];
        $fields = [];
        if ($sfConnected) {
            $objects = $service->getObjects();
            // Get fields for currently selected object
            $selectedObject = $fieldMapping['chance_object'] ?? 'Chance__c';
            $fields = $service->getObjectFields($selectedObject);
        }

        return view('admin.settings.salesforce', compact(
            'sfConnected',
            'sfInstanceUrl',
            'sfClientId',
            'sfConnectedAt',
            'sfLastSyncAt',
            'fieldMapping',
            'objects',
            'fields'
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

    /**
     * Get Salesforce objects (AJAX)
     */
    public function getObjects()
    {
        $service = new SalesforceService();

        if (!$service->isConnected()) {
            return response()->json([]);
        }

        return response()->json($service->getObjects());
    }

    /**
     * Get fields for a Salesforce object (AJAX)
     */
    public function getObjectFields(Request $request)
    {
        $objectName = $request->get('object');
        if (!$objectName) {
            return response()->json([]);
        }

        $service = new SalesforceService();

        if (!$service->isConnected()) {
            return response()->json([]);
        }

        return response()->json($service->getObjectFields($objectName));
    }

    /**
     * Save field mapping
     */
    public function saveFieldMapping(Request $request)
    {
        $validated = $request->validate([
            'chance_object' => 'required|string',
            'ctm_call_id_field' => 'required|string',
            'project_field' => 'nullable|string',
            'land_sale_field' => 'nullable|string',
            'contact_status_field' => 'nullable|string',
            'appointment_made_field' => 'nullable|string',
            'toured_property_field' => 'nullable|string',
            'opportunity_created_field' => 'nullable|string',
            'office_field' => 'nullable|string',
        ]);

        $service = new SalesforceService();
        $service->setFieldMapping($validated);

        return back()->with('success', 'Field mapping saved.');
    }

    /**
     * Sync Chances from Salesforce
     */
    public function syncChances(Request $request)
    {
        $validated = $request->validate([
            'hours' => 'required|integer|min:1|max:720',
        ]);

        $service = new SalesforceService();

        if (!$service->isConnected()) {
            return back()->with('error', 'Salesforce is not connected.');
        }

        $result = $service->syncChancesByTimeRange($validated['hours']);

        if ($result['success']) {
            return back()->with('success', $result['message'] . " ({$result['total_chances']} chances found, {$result['not_found']} not matched)");
        }

        return back()->with('error', $result['message']);
    }

    public function getUsers()
    {
        $service = new SalesforceService();

        if (!$service->isConnected()) {
            return response()->json([]);
        }

        return response()->json($service->getUsers());
    }
}
