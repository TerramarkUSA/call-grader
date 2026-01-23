<?php

namespace App\Services;

use App\Models\Call;
use App\Models\Rep;
use App\Models\Project;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SalesforceService
{
    // ==================
    // OAuth Methods
    // ==================

    public function getAuthorizationUrl(string $redirectUri): string
    {
        $params = http_build_query([
            'response_type' => 'code',
            'client_id' => Setting::get('sf_client_id'),
            'redirect_uri' => $redirectUri,
            'scope' => 'api refresh_token',
        ]);

        return Setting::get('sf_instance_url') . '/services/oauth2/authorize?' . $params;
    }

    public function handleCallback(string $code, string $redirectUri): bool
    {
        $instanceUrl = Setting::get('sf_instance_url');
        $clientId = Setting::get('sf_client_id');
        $clientSecret = Setting::getEncrypted('sf_client_secret');

        $response = Http::asForm()->post($instanceUrl . '/services/oauth2/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri,
            'code' => $code,
        ]);

        if (!$response->successful()) {
            Log::error('Salesforce OAuth failed', ['response' => $response->body()]);
            return false;
        }

        $data = $response->json();

        Setting::setEncrypted('sf_access_token', $data['access_token']);
        Setting::setEncrypted('sf_refresh_token', $data['refresh_token']);
        Setting::set('sf_token_expires_at', now()->addSeconds($data['expires_in'] ?? 7200)->toIso8601String());
        Setting::set('sf_connected_at', now()->toIso8601String());

        return true;
    }

    public function refreshToken(): bool
    {
        $refreshToken = Setting::getEncrypted('sf_refresh_token');
        if (!$refreshToken) {
            return false;
        }

        $instanceUrl = Setting::get('sf_instance_url');
        $clientId = Setting::get('sf_client_id');
        $clientSecret = Setting::getEncrypted('sf_client_secret');

        $response = Http::asForm()->post($instanceUrl . '/services/oauth2/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $refreshToken,
        ]);

        if (!$response->successful()) {
            Log::error('Salesforce token refresh failed', ['response' => $response->body()]);
            return false;
        }

        $data = $response->json();

        Setting::setEncrypted('sf_access_token', $data['access_token']);
        Setting::set('sf_token_expires_at', now()->addSeconds($data['expires_in'] ?? 7200)->toIso8601String());

        return true;
    }

    public function isConnected(): bool
    {
        return Setting::get('sf_connected_at') !== null
            && Setting::getEncrypted('sf_refresh_token') !== null;
    }

    public function disconnect(): void
    {
        Setting::set('sf_access_token', null);
        Setting::set('sf_refresh_token', null);
        Setting::set('sf_token_expires_at', null);
        Setting::set('sf_connected_at', null);
    }

    protected function getAccessToken(): ?string
    {
        $accessToken = Setting::getEncrypted('sf_access_token');
        if (!$accessToken) {
            return null;
        }

        // Refresh if expired or expiring soon
        $expiresAt = Setting::get('sf_token_expires_at');
        if ($expiresAt && now()->gt(now()->parse($expiresAt)->subMinutes(5))) {
            if (!$this->refreshToken()) {
                return null;
            }
            $accessToken = Setting::getEncrypted('sf_access_token');
        }

        return $accessToken;
    }

    // ==================
    // Query Methods
    // ==================

    public function query(string $soql): ?array
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return null;
        }

        $instanceUrl = Setting::get('sf_instance_url');

        $response = Http::withToken($token)
            ->get($instanceUrl . '/services/data/v59.0/query', [
                'q' => $soql,
            ]);

        if (!$response->successful()) {
            Log::error('Salesforce query failed', [
                'soql' => $soql,
                'response' => $response->body()
            ]);
            return null;
        }

        return $response->json();
    }

    public function getChanceByCtmCallId(string $ctmCallId): ?array
    {
        $mapping = $this->getFieldMapping();

        // Escape single quotes in CTM Call ID
        $ctmCallId = str_replace("'", "\\'", $ctmCallId);

        $soql = "SELECT Id, Lead__c, OwnerId,
                 {$mapping['project_field']},
                 {$mapping['land_sale_field']}, Land_Sale__r.Name,
                 {$mapping['contact_status_field']},
                 {$mapping['appointment_made_field']},
                 {$mapping['toured_property_field']},
                 {$mapping['opportunity_created_field']}
                 FROM {$mapping['chance_object']}
                 WHERE {$mapping['ctm_call_id_field']} = '{$ctmCallId}'
                 LIMIT 1";

        $results = $this->query($soql);

        return $results['records'][0] ?? null;
    }

    public function getUsers(): array
    {
        $results = $this->query("SELECT Id, Name, Email FROM User WHERE IsActive = true ORDER BY Name");
        return $results['records'] ?? [];
    }

    // ==================
    // Schema Discovery Methods
    // ==================

    /**
     * Get list of Salesforce objects (custom and standard)
     */
    public function getObjects(): array
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return [];
        }

        $instanceUrl = Setting::get('sf_instance_url');

        $response = Http::withToken($token)
            ->get($instanceUrl . '/services/data/v59.0/sobjects/');

        if (!$response->successful()) {
            Log::error('Salesforce getObjects failed', ['response' => $response->body()]);
            return [];
        }

        $data = $response->json();
        $objects = [];

        foreach ($data['sobjects'] ?? [] as $obj) {
            // Include custom objects and commonly used standard objects
            if ($obj['custom'] || in_array($obj['name'], ['Lead', 'Contact', 'Account', 'Opportunity', 'Case', 'Task', 'Event'])) {
                $objects[] = [
                    'name' => $obj['name'],
                    'label' => $obj['label'],
                    'custom' => $obj['custom'],
                ];
            }
        }

        // Sort by label
        usort($objects, fn($a, $b) => strcmp($a['label'], $b['label']));

        return $objects;
    }

    /**
     * Get fields for a specific Salesforce object
     */
    public function getObjectFields(string $objectName): array
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return [];
        }

        $instanceUrl = Setting::get('sf_instance_url');

        $response = Http::withToken($token)
            ->get($instanceUrl . "/services/data/v59.0/sobjects/{$objectName}/describe");

        if (!$response->successful()) {
            Log::error('Salesforce getObjectFields failed', [
                'object' => $objectName,
                'response' => $response->body()
            ]);
            return [];
        }

        $data = $response->json();
        $fields = [];

        foreach ($data['fields'] ?? [] as $field) {
            $fields[] = [
                'name' => $field['name'],
                'label' => $field['label'],
                'type' => $field['type'],
            ];
        }

        // Sort by label
        usort($fields, fn($a, $b) => strcmp($a['label'], $b['label']));

        return $fields;
    }

    // ==================
    // Batch Sync Methods
    // ==================

    /**
     * Sync Chances from Salesforce by time range
     */
    public function syncChancesByTimeRange(int $hours): array
    {
        if (!$this->isConnected()) {
            return ['success' => false, 'message' => 'Salesforce not connected', 'matched' => 0, 'not_found' => 0];
        }

        $mapping = $this->getFieldMapping();
        $objectName = $mapping['chance_object'] ?? 'Chance__c';
        $ctmIdField = $mapping['ctm_call_id_field'] ?? 'CTM_Call_ID__c';

        // Build field list for query
        $fields = ['Id', 'Lead__c', 'OwnerId', $ctmIdField, 'Land_Sale__r.Name'];
        
        $optionalFields = [
            'project_field', 'land_sale_field', 'contact_status_field',
            'appointment_made_field', 'toured_property_field', 'opportunity_created_field'
        ];
        
        foreach ($optionalFields as $key) {
            if (!empty($mapping[$key])) {
                $fields[] = $mapping[$key];
            }
        }

        $fieldList = implode(', ', array_unique($fields));

        // Calculate datetime for hours ago (LAST_N_HOURS is not valid SOQL)
        $since = now()->subHours($hours)->format('Y-m-d\TH:i:s\Z');

        // Query for Chances with CTM Call ID in the time range
        $soql = "SELECT {$fieldList} FROM {$objectName} 
                 WHERE {$ctmIdField} != null 
                 AND CreatedDate >= {$since}
                 ORDER BY CreatedDate DESC
                 LIMIT 2000";

        $results = $this->query($soql);

        if ($results === null) {
            return ['success' => false, 'message' => 'Query failed', 'matched' => 0, 'not_found' => 0];
        }

        $chances = $results['records'] ?? [];
        $matched = 0;
        $notFound = 0;

        foreach ($chances as $chance) {
            $ctmCallId = $chance[$ctmIdField] ?? null;
            if (!$ctmCallId) {
                continue;
            }

            // Find matching call
            $call = Call::where('ctm_activity_id', $ctmCallId)->first();

            if ($call) {
                // Update call with SF data
                $call->update([
                    'sf_chance_id' => $chance['Id'],
                    'sf_lead_id' => $chance['Lead__c'] ?? null,
                    'sf_owner_id' => $chance['OwnerId'] ?? null,
                    'sf_project' => $chance[$mapping['project_field']] ?? null,
                    'sf_land_sale' => $chance['Land_Sale__r']['Name'] ?? null,
                    'sf_contact_status' => $chance[$mapping['contact_status_field']] ?? null,
                    'sf_appointment_made' => $chance[$mapping['appointment_made_field']] ?? null,
                    'sf_toured_property' => $chance[$mapping['toured_property_field']] ?? null,
                    'sf_opportunity_created' => $chance[$mapping['opportunity_created_field']] ?? null,
                    'sf_synced_at' => now(),
                ]);

                // Auto-match rep
                $this->autoMatchRep($call, $chance);

                // Auto-match project
                $this->autoMatchProject($call, $chance, $mapping);

                $matched++;
            } else {
                $notFound++;
            }
        }

        // Update last sync time
        Setting::set('sf_last_sync_at', now()->toIso8601String());

        Log::info('Salesforce batch sync completed', [
            'hours' => $hours,
            'total_chances' => count($chances),
            'matched' => $matched,
            'not_found' => $notFound,
        ]);

        return [
            'success' => true,
            'message' => "Synced {$matched} calls from Salesforce",
            'matched' => $matched,
            'not_found' => $notFound,
            'total_chances' => count($chances),
        ];
    }

    /**
     * Auto-match rep from Chance owner
     */
    protected function autoMatchRep(Call $call, array $chance): void
    {
        if (empty($chance['OwnerId']) || $call->rep_id) {
            return;
        }

        $rep = Rep::where('sf_user_id', $chance['OwnerId'])
                  ->where('account_id', $call->account_id)
                  ->first();

        if ($rep) {
            $call->update(['rep_id' => $rep->id]);
            return;
        }

        // Auto-create Rep from Salesforce User
        $escapedOwnerId = str_replace("'", "\\'", $chance['OwnerId']);
        $soql = "SELECT Id, Name, Email FROM User WHERE Id = '{$escapedOwnerId}' LIMIT 1";
        $results = $this->query($soql);
        $sfUser = $results['records'][0] ?? null;

        if ($sfUser) {
            $rep = Rep::create([
                'account_id' => $call->account_id,
                'name' => $sfUser['Name'],
                'email' => $sfUser['Email'] ?? null,
                'sf_user_id' => $sfUser['Id'],
                'is_active' => true,
            ]);

            Log::info('Auto-created Rep from SF sync', ['name' => $rep->name, 'sf_id' => $sfUser['Id']]);
            $call->update(['rep_id' => $rep->id]);
        }
    }

    /**
     * Auto-match project from Chance data
     */
    protected function autoMatchProject(Call $call, array $chance, array $mapping): void
    {
        $sfProject = $chance[$mapping['project_field']] ?? null;
        if (!$sfProject || $call->project_id) {
            return;
        }

        $project = Project::where('sf_project_name', $sfProject)
                          ->where('account_id', $call->account_id)
                          ->first();

        if ($project) {
            $call->update(['project_id' => $project->id]);
            return;
        }

        // Auto-create Project
        $project = Project::create([
            'account_id' => $call->account_id,
            'name' => $sfProject,
            'sf_project_name' => $sfProject,
            'is_active' => true,
        ]);

        Log::info('Auto-created Project from SF sync', ['name' => $project->name]);
        $call->update(['project_id' => $project->id]);
    }

    public function getFieldMapping(): array
    {
        $defaults = [
            'chance_object' => 'Chance__c',
            'ctm_call_id_field' => 'CTM_Call_ID__c',
            'project_field' => 'Project__c',
            'land_sale_field' => 'Land_Sale__c',
            'contact_status_field' => 'Contact_Status__c',
            'appointment_made_field' => 'Appointment_Made__c',
            'toured_property_field' => 'Toured_Property__c',
            'opportunity_created_field' => 'Opportunity_Created__c',
            'office_field' => 'Project_Office__c',
        ];

        $stored = Setting::get('sf_field_mapping');
        if ($stored && is_string($stored)) {
            $stored = json_decode($stored, true) ?? [];
        }

        return array_merge($defaults, $stored ?? []);
    }

    public function setFieldMapping(array $mapping): void
    {
        Setting::set('sf_field_mapping', json_encode($mapping));
    }

    // ==================
    // Enrichment Methods
    // ==================

    public function enrichCall(Call $call): bool
    {
        if (!$call->ctm_activity_id || !$this->isConnected()) {
            return false;
        }

        $chance = $this->getChanceByCtmCallId($call->ctm_activity_id);

        if (!$chance) {
            Log::info('Salesforce Chance not found for call', ['call_id' => $call->id, 'ctm_activity_id' => $call->ctm_activity_id]);
            return false;
        }

        $mapping = $this->getFieldMapping();

        // Update call with SF data
        $call->update([
            'sf_chance_id' => $chance['Id'],
            'sf_lead_id' => $chance['Lead__c'] ?? null,
            'sf_owner_id' => $chance['OwnerId'] ?? null,
            'sf_project' => $chance[$mapping['project_field']] ?? null,
            'sf_land_sale' => $chance['Land_Sale__r']['Name'] ?? null,
            'sf_contact_status' => $chance[$mapping['contact_status_field']] ?? null,
            'sf_appointment_made' => $chance[$mapping['appointment_made_field']] ?? null,
            'sf_toured_property' => $chance[$mapping['toured_property_field']] ?? null,
            'sf_opportunity_created' => $chance[$mapping['opportunity_created_field']] ?? null,
            'sf_synced_at' => now(),
        ]);

        // Auto-match rep by SF Owner ID
        if ($chance['OwnerId'] && !$call->rep_id) {
            $rep = Rep::where('sf_user_id', $chance['OwnerId'])
                      ->where('account_id', $call->account_id)
                      ->first();
            
            if ($rep) {
                $call->update(['rep_id' => $rep->id]);
            } else {
                // Auto-create Rep from Salesforce User
                $ownerId = $chance['OwnerId'];
                $escapedOwnerId = str_replace("'", "\\'", $ownerId);
                $soql = "SELECT Id, Name, Email FROM User WHERE Id = '{$escapedOwnerId}' LIMIT 1";
                $results = $this->query($soql);
                $sfUser = $results['records'][0] ?? null;
                
                if ($sfUser) {
                    $rep = Rep::create([
                        'account_id' => $call->account_id,
                        'name' => $sfUser['Name'],
                        'email' => $sfUser['Email'] ?? null,
                        'sf_user_id' => $sfUser['Id'],
                        'is_active' => true,
                    ]);
                    
                    Log::info('Auto-created Rep: {name} from SF User {id}', [
                        'name' => $rep->name,
                        'id' => $sfUser['Id'],
                    ]);
                    
                    $call->update(['rep_id' => $rep->id]);
                }
            }
        }

        // Auto-match project by SF Project name
        $sfProject = $chance[$mapping['project_field']] ?? null;
        if ($sfProject && !$call->project_id) {
            $project = Project::where('sf_project_name', $sfProject)
                              ->where('account_id', $call->account_id)
                              ->first();
            
            if ($project) {
                $call->update(['project_id' => $project->id]);
            } else {
                // Auto-create Project from Salesforce Project__c value
                $project = Project::create([
                    'account_id' => $call->account_id,
                    'name' => $sfProject,
                    'sf_project_name' => $sfProject,
                    'is_active' => true,
                ]);
                
                Log::info('Auto-created Project: {name} from SF', [
                    'name' => $project->name,
                ]);
                
                $call->update(['project_id' => $project->id]);
            }
        }

        return true;
    }

    public function refreshOutcomes(Call $call): bool
    {
        if (!$call->ctm_activity_id || !$this->isConnected()) {
            return false;
        }

        $chance = $this->getChanceByCtmCallId($call->ctm_activity_id);

        if (!$chance) {
            return false;
        }

        $mapping = $this->getFieldMapping();

        $call->update([
            'sf_appointment_made' => $chance[$mapping['appointment_made_field']] ?? null,
            'sf_toured_property' => $chance[$mapping['toured_property_field']] ?? null,
            'sf_opportunity_created' => $chance[$mapping['opportunity_created_field']] ?? null,
            'sf_outcome_synced_at' => now(),
        ]);

        return true;
    }
}
