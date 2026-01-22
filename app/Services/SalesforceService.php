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
                 {$mapping['land_sale_field']},
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
            'sf_land_sale' => $chance[$mapping['land_sale_field']] ?? null,
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
