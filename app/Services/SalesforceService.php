<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Call;
use App\Models\Rep;
use App\Models\Project;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class SalesforceService
{
    protected Account $account;

    public function __construct(Account $account)
    {
        $this->account = $account;
    }

    // ==================
    // OAuth Methods
    // ==================

    public function getAuthorizationUrl(string $redirectUri): string
    {
        $params = http_build_query([
            'response_type' => 'code',
            'client_id' => $this->account->sf_client_id,
            'redirect_uri' => $redirectUri,
            'scope' => 'api refresh_token',
        ]);

        return $this->account->sf_instance_url . '/services/oauth2/authorize?' . $params;
    }

    public function handleCallback(string $code, string $redirectUri): bool
    {
        $response = Http::asForm()->post($this->account->sf_instance_url . '/services/oauth2/token', [
            'grant_type' => 'authorization_code',
            'client_id' => $this->account->sf_client_id,
            'client_secret' => $this->getDecryptedSecret(),
            'redirect_uri' => $redirectUri,
            'code' => $code,
        ]);

        if (!$response->successful()) {
            Log::error('Salesforce OAuth failed', ['response' => $response->body()]);
            return false;
        }

        $data = $response->json();

        $this->account->update([
            'sf_access_token' => Crypt::encryptString($data['access_token']),
            'sf_refresh_token' => Crypt::encryptString($data['refresh_token']),
            'sf_token_expires_at' => now()->addSeconds($data['expires_in'] ?? 7200),
            'sf_connected_at' => now(),
        ]);

        return true;
    }

    public function refreshToken(): bool
    {
        if (!$this->account->sf_refresh_token) {
            return false;
        }

        $response = Http::asForm()->post($this->account->sf_instance_url . '/services/oauth2/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $this->account->sf_client_id,
            'client_secret' => $this->getDecryptedSecret(),
            'refresh_token' => Crypt::decryptString($this->account->sf_refresh_token),
        ]);

        if (!$response->successful()) {
            Log::error('Salesforce token refresh failed', ['response' => $response->body()]);
            return false;
        }

        $data = $response->json();

        $this->account->update([
            'sf_access_token' => Crypt::encryptString($data['access_token']),
            'sf_token_expires_at' => now()->addSeconds($data['expires_in'] ?? 7200),
        ]);

        return true;
    }

    public function isConnected(): bool
    {
        return $this->account->sf_connected_at !== null
            && $this->account->sf_refresh_token !== null;
    }

    public function disconnect(): void
    {
        $this->account->update([
            'sf_access_token' => null,
            'sf_refresh_token' => null,
            'sf_token_expires_at' => null,
            'sf_connected_at' => null,
        ]);
    }

    protected function getAccessToken(): ?string
    {
        if (!$this->account->sf_access_token) {
            return null;
        }

        // Refresh if expired or expiring soon
        if ($this->account->sf_token_expires_at?->lt(now()->addMinutes(5))) {
            if (!$this->refreshToken()) {
                return null;
            }
            $this->account->refresh();
        }

        return Crypt::decryptString($this->account->sf_access_token);
    }

    protected function getDecryptedSecret(): ?string
    {
        if (!$this->account->sf_client_secret) {
            return null;
        }
        return Crypt::decryptString($this->account->sf_client_secret);
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

        $response = Http::withToken($token)
            ->get($this->account->sf_instance_url . '/services/data/v59.0/query', [
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
        ];

        return array_merge($defaults, $this->account->sf_field_mapping ?? []);
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
