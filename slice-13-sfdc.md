# Slice 13: Salesforce Integration

## Overview

Integrate Salesforce to enrich calls with Chance data. Read-only for V1 (no write-back).

**Two sync types:**
1. **Initial Enrichment** — 15 min after CTM sync, 3 retry attempts
2. **Daily Outcome Refresh** — 2am, updates calls from last 90 days

**Data pulled:**
- Initial: Rep (Owner), Project (Project__c match), Land Sale, Appointment Made
- Daily: Appointment Made, Toured Property, Opportunity Created

---

## 1. MIGRATIONS

### Migration: add_salesforce_fields_to_accounts_table

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->string('sf_instance_url')->nullable();
            $table->string('sf_client_id')->nullable();
            $table->text('sf_client_secret')->nullable();
            $table->text('sf_refresh_token')->nullable();
            $table->text('sf_access_token')->nullable();
            $table->timestamp('sf_token_expires_at')->nullable();
            $table->timestamp('sf_connected_at')->nullable();
            $table->json('sf_field_mapping')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn([
                'sf_instance_url',
                'sf_client_id',
                'sf_client_secret',
                'sf_refresh_token',
                'sf_access_token',
                'sf_token_expires_at',
                'sf_connected_at',
                'sf_field_mapping',
            ]);
        });
    }
};
```

### Migration: add_salesforce_fields_to_calls_table

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            $table->string('sf_chance_id', 18)->nullable()->index();
            $table->string('sf_lead_id', 18)->nullable();
            $table->string('sf_owner_id', 18)->nullable();
            $table->string('sf_project')->nullable();
            $table->string('sf_land_sale')->nullable();
            $table->string('sf_contact_status')->nullable();
            $table->boolean('sf_appointment_made')->nullable();
            $table->boolean('sf_toured_property')->nullable();
            $table->boolean('sf_opportunity_created')->nullable();
            $table->timestamp('sf_synced_at')->nullable();
            $table->timestamp('sf_outcome_synced_at')->nullable();
            $table->unsignedTinyInteger('sf_sync_attempts')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('calls', function (Blueprint $table) {
            $table->dropColumn([
                'sf_chance_id',
                'sf_lead_id',
                'sf_owner_id',
                'sf_project',
                'sf_land_sale',
                'sf_contact_status',
                'sf_appointment_made',
                'sf_toured_property',
                'sf_opportunity_created',
                'sf_synced_at',
                'sf_outcome_synced_at',
                'sf_sync_attempts',
            ]);
        });
    }
};
```

### Migration: add_salesforce_user_id_to_reps_table

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reps', function (Blueprint $table) {
            $table->string('sf_user_id', 18)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('reps', function (Blueprint $table) {
            $table->dropColumn('sf_user_id');
        });
    }
};
```

### Migration: add_salesforce_project_name_to_projects_table

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('sf_project_name')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('sf_project_name');
        });
    }
};
```

---

## 2. SALESFORCE SERVICE

Create app/Services/SalesforceService.php:

```php
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
        if (!$call->ctm_call_id || !$this->isConnected()) {
            return false;
        }

        $chance = $this->getChanceByCtmCallId($call->ctm_call_id);

        if (!$chance) {
            Log::info('Salesforce Chance not found for call', ['call_id' => $call->id, 'ctm_call_id' => $call->ctm_call_id]);
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
        if (!$call->ctm_call_id || !$this->isConnected()) {
            return false;
        }

        $chance = $this->getChanceByCtmCallId($call->ctm_call_id);

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
```

---

## 3. JOBS

### Job: EnrichCallFromSalesforce

Create app/Jobs/EnrichCallFromSalesforce.php:

```php
<?php

namespace App\Jobs;

use App\Models\Call;
use App\Services\SalesforceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class EnrichCallFromSalesforce implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [900, 900, 1800]; // 15min, 15min, 30min

    public function __construct(
        public Call $call
    ) {}

    public function handle(): void
    {
        $account = $this->call->account;

        if (!$account || !$account->sf_connected_at) {
            Log::info('Salesforce not connected, skipping enrichment', ['call_id' => $this->call->id]);
            return;
        }

        $service = new SalesforceService($account);
        $success = $service->enrichCall($this->call);

        if (!$success) {
            $this->call->increment('sf_sync_attempts');
            
            if ($this->attempts() < $this->tries) {
                Log::info('Salesforce Chance not found, will retry', [
                    'call_id' => $this->call->id,
                    'attempt' => $this->attempts()
                ]);
                throw new \Exception('Chance not found in Salesforce, will retry');
            }
            
            Log::warning('Salesforce enrichment failed after all retries', ['call_id' => $this->call->id]);
        }
    }
}
```

### Job: RefreshCallOutcomes

Create app/Jobs/RefreshCallOutcomes.php:

```php
<?php

namespace App\Jobs;

use App\Models\Account;
use App\Models\Call;
use App\Services\SalesforceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RefreshCallOutcomes implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $accounts = Account::whereNotNull('sf_connected_at')->get();

        foreach ($accounts as $account) {
            $service = new SalesforceService($account);

            $calls = Call::where('account_id', $account->id)
                ->whereNotNull('sf_chance_id')
                ->where('call_date', '>=', now()->subDays(90))
                ->get();

            $updated = 0;
            foreach ($calls as $call) {
                if ($service->refreshOutcomes($call)) {
                    $updated++;
                }
            }

            Log::info('Refreshed Salesforce outcomes', [
                'account' => $account->name,
                'calls_updated' => $updated
            ]);
        }
    }
}
```

### Schedule Daily Job

In app/Console/Kernel.php, add to schedule():

```php
$schedule->job(new \App\Jobs\RefreshCallOutcomes)->dailyAt('02:00');
```

---

## 4. DISPATCH AFTER CTM SYNC

In your CTMService (or wherever calls are synced), after saving new calls:

```php
use App\Jobs\EnrichCallFromSalesforce;

// After saving new calls from CTM
foreach ($newCalls as $call) {
    EnrichCallFromSalesforce::dispatch($call)
        ->delay(now()->addMinutes(15));
}
```

---

## 5. ADMIN CONTROLLER

Create app/Http/Controllers/Admin/SalesforceController.php:

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Rep;
use App\Models\Project;
use App\Services\SalesforceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Inertia\Inertia;

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

        return Inertia::render('Admin/Settings/Salesforce', [
            'accounts' => $accounts,
            'reps' => $reps,
            'projects' => $projects,
        ]);
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
            return redirect()->route('admin.settings.index', ['tab' => 'salesforce'])
                ->with('error', 'Salesforce authorization failed: ' . $request->get('error_description'));
        }

        $service = new SalesforceService($account);
        $redirectUri = route('admin.salesforce.callback', ['account' => $account->id]);
        
        if ($service->handleCallback($request->get('code'), $redirectUri)) {
            return redirect()->route('admin.settings.index', ['tab' => 'salesforce'])
                ->with('success', 'Salesforce connected successfully.');
        }

        return redirect()->route('admin.settings.index', ['tab' => 'salesforce'])
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
```

---

## 6. ROUTES

Add to routes/admin.php:

```php
use App\Http\Controllers\Admin\SalesforceController;

// Salesforce routes (under settings)
Route::prefix('salesforce')->group(function () {
    Route::get('/', [SalesforceController::class, 'index'])->name('salesforce.index');
    Route::post('/{account}/credentials', [SalesforceController::class, 'saveCredentials'])->name('salesforce.credentials');
    Route::get('/{account}/connect', [SalesforceController::class, 'connect'])->name('salesforce.connect');
    Route::get('/{account}/callback', [SalesforceController::class, 'callback'])->name('salesforce.callback');
    Route::post('/{account}/disconnect', [SalesforceController::class, 'disconnect'])->name('salesforce.disconnect');
    Route::post('/{account}/test', [SalesforceController::class, 'testConnection'])->name('salesforce.test');
    Route::post('/{account}/field-mapping', [SalesforceController::class, 'saveFieldMapping'])->name('salesforce.field-mapping');
    Route::get('/{account}/users', [SalesforceController::class, 'getUsers'])->name('salesforce.users');
    Route::post('/{account}/auto-match-reps', [SalesforceController::class, 'autoMatchReps'])->name('salesforce.auto-match-reps');
    Route::post('/rep-mapping', [SalesforceController::class, 'saveRepMapping'])->name('salesforce.rep-mapping');
    Route::post('/project-mapping', [SalesforceController::class, 'saveProjectMapping'])->name('salesforce.project-mapping');
});
```

---

## 7. SETTINGS PAGE - ADD SALESFORCE TAB

Update the existing Settings page to include a Salesforce tab.

The Settings page should have tabs:
- General
- Deepgram  
- Salesforce (new)

### Salesforce Tab Content

```vue
<template>
  <div class="space-y-6">
    <!-- Per-Account Connection -->
    <div v-for="account in accounts" :key="account.id" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
      <div class="flex items-center justify-between mb-4">
        <div>
          <h3 class="text-lg font-semibold text-gray-900">{{ account.name }}</h3>
          <p class="text-sm text-gray-500">
            <span v-if="account.sf_connected" class="text-green-600">● Connected</span>
            <span v-else class="text-gray-400">● Not connected</span>
            <span v-if="account.sf_connected_at" class="ml-2">
              since {{ formatDate(account.sf_connected_at) }}
            </span>
          </p>
        </div>
        <div v-if="account.sf_connected" class="flex gap-2">
          <button @click="testConnection(account)" class="text-sm text-blue-600 hover:text-blue-700">
            Test
          </button>
          <button @click="disconnect(account)" class="text-sm text-red-600 hover:text-red-700">
            Disconnect
          </button>
        </div>
      </div>

      <!-- Credentials Form (if not connected) -->
      <div v-if="!account.sf_connected" class="space-y-4">
        <div class="grid grid-cols-1 gap-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Instance URL</label>
            <input 
              v-model="credentials[account.id].sf_instance_url"
              type="url"
              placeholder="https://yourorg.my.salesforce.com"
              class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm"
            />
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Client ID</label>
              <input 
                v-model="credentials[account.id].sf_client_id"
                type="text"
                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm"
              />
            </div>
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-1">Client Secret</label>
              <input 
                v-model="credentials[account.id].sf_client_secret"
                type="password"
                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm"
              />
            </div>
          </div>
        </div>
        <div class="flex gap-2">
          <button @click="saveCredentials(account)" class="bg-gray-100 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-200">
            Save Credentials
          </button>
          <button @click="connect(account)" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700">
            Connect to Salesforce
          </button>
        </div>
      </div>

      <!-- Field Mapping (if connected) -->
      <div v-if="account.sf_connected" class="mt-6 pt-6 border-t border-gray-100">
        <h4 class="font-medium text-gray-900 mb-4">Field Mapping</h4>
        <div class="grid grid-cols-2 gap-4 text-sm">
          <div>
            <label class="block text-gray-600 mb-1">Chance Object</label>
            <input v-model="fieldMappings[account.id].chance_object" class="w-full border border-gray-200 rounded-lg px-3 py-2" />
          </div>
          <div>
            <label class="block text-gray-600 mb-1">CTM Call ID Field</label>
            <input v-model="fieldMappings[account.id].ctm_call_id_field" class="w-full border border-gray-200 rounded-lg px-3 py-2" />
          </div>
          <div>
            <label class="block text-gray-600 mb-1">Project Field</label>
            <input v-model="fieldMappings[account.id].project_field" class="w-full border border-gray-200 rounded-lg px-3 py-2" />
          </div>
          <div>
            <label class="block text-gray-600 mb-1">Land Sale Field</label>
            <input v-model="fieldMappings[account.id].land_sale_field" class="w-full border border-gray-200 rounded-lg px-3 py-2" />
          </div>
          <div>
            <label class="block text-gray-600 mb-1">Appointment Made Field</label>
            <input v-model="fieldMappings[account.id].appointment_made_field" class="w-full border border-gray-200 rounded-lg px-3 py-2" />
          </div>
          <div>
            <label class="block text-gray-600 mb-1">Toured Property Field</label>
            <input v-model="fieldMappings[account.id].toured_property_field" class="w-full border border-gray-200 rounded-lg px-3 py-2" />
          </div>
          <div>
            <label class="block text-gray-600 mb-1">Opportunity Created Field</label>
            <input v-model="fieldMappings[account.id].opportunity_created_field" class="w-full border border-gray-200 rounded-lg px-3 py-2" />
          </div>
        </div>
        <button @click="saveFieldMapping(account)" class="mt-4 bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700">
          Save Field Mapping
        </button>
      </div>
    </div>

    <!-- Rep Mapping -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
      <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold text-gray-900">Rep Mapping</h3>
        <button @click="autoMatchReps" class="text-sm text-blue-600 hover:text-blue-700">
          Auto-Match by Email
        </button>
      </div>
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-gray-100">
            <th class="text-left py-2 text-gray-500 font-medium">Rep</th>
            <th class="text-left py-2 text-gray-500 font-medium">Office</th>
            <th class="text-left py-2 text-gray-500 font-medium">Salesforce User</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="rep in reps" :key="rep.id" class="border-b border-gray-50">
            <td class="py-3">{{ rep.name }}</td>
            <td class="py-3 text-gray-500">{{ rep.account?.name }}</td>
            <td class="py-3">
              <select v-model="repMappings[rep.id]" class="border border-gray-200 rounded-lg px-2 py-1 text-sm w-full">
                <option value="">Select User...</option>
                <option v-for="user in sfUsers" :key="user.Id" :value="user.Id">
                  {{ user.Name }}
                </option>
              </select>
            </td>
          </tr>
        </tbody>
      </table>
      <button @click="saveRepMapping" class="mt-4 bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700">
        Save Rep Mapping
      </button>
    </div>

    <!-- Project Mapping -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
      <h3 class="text-lg font-semibold text-gray-900 mb-4">Project Mapping</h3>
      <p class="text-sm text-gray-500 mb-4">Map your projects to the Salesforce Project__c field value</p>
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-gray-100">
            <th class="text-left py-2 text-gray-500 font-medium">Project</th>
            <th class="text-left py-2 text-gray-500 font-medium">Office</th>
            <th class="text-left py-2 text-gray-500 font-medium">SF Project Name</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="project in projects" :key="project.id" class="border-b border-gray-50">
            <td class="py-3">{{ project.name }}</td>
            <td class="py-3 text-gray-500">{{ project.account?.name }}</td>
            <td class="py-3">
              <input 
                v-model="projectMappings[project.id]" 
                type="text"
                placeholder="e.g., Hilltop Ranch"
                class="border border-gray-200 rounded-lg px-2 py-1 text-sm w-full"
              />
            </td>
          </tr>
        </tbody>
      </table>
      <button @click="saveProjectMapping" class="mt-4 bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700">
        Save Project Mapping
      </button>
    </div>
  </div>
</template>
```

---

## 8. UPDATE CALL QUEUE UI

Add new columns to the calls table. Update the CallQueueController to include SF fields.

### Controller Update

```php
// In CallQueueController index method, add to the select:
$calls = Call::with(['rep:id,name', 'project:id,name'])
    ->select([
        // existing fields...
        'sf_project',
        'sf_land_sale',
        'sf_appointment_made',
        'sf_toured_property',
        'sf_opportunity_created',
        'sf_synced_at',
    ])
    // rest of query...
```

### Table Columns

Add after PROJECT column:

| LAND SALE | APPT | TOURED | CONTRACT |

```vue
<!-- Land Sale Column -->
<td class="px-4 py-4 text-sm text-gray-600">
  {{ call.sf_land_sale || '—' }}
</td>

<!-- Appointment Column -->
<td class="px-4 py-4 text-center">
  <span v-if="call.sf_appointment_made === true" class="text-green-600">✓</span>
  <span v-else class="text-gray-300">—</span>
</td>

<!-- Toured Column -->
<td class="px-4 py-4 text-center">
  <span v-if="call.sf_toured_property === true" class="text-green-600">✓</span>
  <span v-else class="text-gray-300">—</span>
</td>

<!-- Contract Column -->
<td class="px-4 py-4 text-center">
  <span v-if="call.sf_opportunity_created === true" class="text-green-600">✓</span>
  <span v-else class="text-gray-300">—</span>
</td>
```

---

## 9. UPDATE CALL MODEL

Add casts:

```php
protected $casts = [
    // existing casts...
    'sf_appointment_made' => 'boolean',
    'sf_toured_property' => 'boolean',
    'sf_opportunity_created' => 'boolean',
    'sf_synced_at' => 'datetime',
    'sf_outcome_synced_at' => 'datetime',
    'sf_field_mapping' => 'array',
];
```

---

## 10. GRADING PAGE - SHOW SF DATA

In the Call Details section, show Salesforce enrichment:

```vue
<!-- After Rep/Project/Outcome dropdowns -->
<div v-if="call.sf_synced_at" class="mt-4 pt-4 border-t border-gray-100">
  <div class="flex items-center justify-between mb-2">
    <span class="text-xs text-gray-400">Salesforce Data</span>
    <button @click="refreshSalesforce" class="text-xs text-blue-600 hover:text-blue-700">
      Refresh
    </button>
  </div>
  
  <div class="grid grid-cols-2 gap-2 text-sm">
    <div v-if="call.sf_land_sale">
      <span class="text-gray-500">Land Sale:</span>
      <span class="ml-1 text-gray-900">{{ call.sf_land_sale }}</span>
    </div>
    <div>
      <span class="text-gray-500">Appointment:</span>
      <span v-if="call.sf_appointment_made" class="ml-1 text-green-600">✓ Yes</span>
      <span v-else class="ml-1 text-gray-400">— No</span>
    </div>
    <div>
      <span class="text-gray-500">Toured:</span>
      <span v-if="call.sf_toured_property" class="ml-1 text-green-600">✓ Yes</span>
      <span v-else class="ml-1 text-gray-400">— No</span>
    </div>
    <div>
      <span class="text-gray-500">Contract:</span>
      <span v-if="call.sf_opportunity_created" class="ml-1 text-green-600">✓ Yes</span>
      <span v-else class="ml-1 text-gray-400">— No</span>
    </div>
  </div>
  
  <p class="text-xs text-gray-400 mt-2">
    Synced {{ formatRelative(call.sf_synced_at) }}
  </p>
</div>
```

Add a refresh endpoint in GradingController:

```php
public function refreshSalesforce(Call $call)
{
    $account = $call->account;
    
    if (!$account->sf_connected_at) {
        return response()->json(['error' => 'Salesforce not connected'], 400);
    }
    
    $service = new SalesforceService($account);
    $success = $service->enrichCall($call);
    
    if ($success) {
        return response()->json(['success' => true, 'call' => $call->fresh()]);
    }
    
    return response()->json(['error' => 'Failed to refresh'], 400);
}
```

---

## 11. VERIFICATION CHECKLIST

After implementation:
- [ ] Migrations run successfully
- [ ] Can save Salesforce credentials per account
- [ ] OAuth flow redirects to Salesforce and back
- [ ] Can configure field mapping with custom API names
- [ ] Can map reps to SF users (manual + auto-match by email)
- [ ] Can map projects to SF project names
- [ ] Test connection button works
- [ ] New calls get EnrichCallFromSalesforce job dispatched (15 min delay)
- [ ] Enrichment job retries 3 times if Chance not found
- [ ] Enrichment populates: rep, project, land sale, appointment made
- [ ] Daily RefreshCallOutcomes job scheduled at 2am
- [ ] Daily job updates: appointment, toured, opportunity for 90-day window
- [ ] Call Queue shows columns: Land Sale, Appt, Toured, Contract
- [ ] Grading page shows SF data section with Refresh button
- [ ] Disconnect clears tokens
- [ ] Settings page has Salesforce tab

---

## NOTES FOR SALESFORCE DEV

Field API names can be configured in Settings > Salesforce > Field Mapping.

Defaults:
- Chance Object: `Chance__c`
- CTM Call ID: `CTM_Call_ID__c`
- Project: `Project__c`
- Land Sale: `Land_Sale__c`
- Appointment Made: `Appointment_Made__c`
- Toured Property: `Toured_Property__c`
- Opportunity Created: `Opportunity_Created__c`

If your field names differ, update the mapping in the admin UI.

To add more fields later:
1. Add column to calls migration
2. Add to SalesforceService::getChanceByCtmCallId() SOQL
3. Add to enrichCall() and refreshOutcomes() methods
4. Add to field mapping UI
