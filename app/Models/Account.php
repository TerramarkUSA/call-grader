<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Crypt;

class Account extends Model
{
    protected $fillable = [
        'name',
        'ctm_account_id',
        'ctm_api_key',
        'ctm_api_secret',
        'is_active',
        'allow_multiple_grades',
        'sync_settings',
        'last_sync_at',
        // Salesforce integration (legacy per-account fields kept for migration)
        'sf_instance_url',
        'sf_client_id',
        'sf_client_secret',
        'sf_access_token',
        'sf_refresh_token',
        'sf_token_expires_at',
        'sf_connected_at',
        'sf_field_mapping',
        'sf_office_name',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'allow_multiple_grades' => 'boolean',
        'sync_settings' => 'array',
        'last_sync_at' => 'datetime',
        // Salesforce
        'sf_token_expires_at' => 'datetime',
        'sf_connected_at' => 'datetime',
        'sf_field_mapping' => 'array',
    ];

    public function setCtmApiKeyAttribute($value)
    {
        $this->attributes['ctm_api_key'] = Crypt::encryptString($value);
    }

    public function getCtmApiKeyAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function setCtmApiSecretAttribute($value)
    {
        $this->attributes['ctm_api_secret'] = Crypt::encryptString($value);
    }

    public function getCtmApiSecretAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_accounts');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function reps(): HasMany
    {
        return $this->hasMany(Rep::class);
    }

    public function calls(): HasMany
    {
        return $this->hasMany(Call::class);
    }
}
