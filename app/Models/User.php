<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'email_verified_at' => 'datetime',
        'last_seen_updates_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function accounts(): BelongsToMany
    {
        return $this->belongsToMany(Account::class, 'user_accounts');
    }

    public function magicLinks(): HasMany
    {
        return $this->hasMany(MagicLink::class);
    }

    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class, 'graded_by');
    }

    public function coachingNotes(): HasMany
    {
        return $this->hasMany(CoachingNote::class, 'author_id');
    }

    public function isSystemAdmin(): bool
    {
        return $this->role === 'system_admin';
    }

    public function isSiteAdmin(): bool
    {
        return in_array($this->role, ['system_admin', 'site_admin']);
    }

    public function isManager(): bool
    {
        return in_array($this->role, ['system_admin', 'site_admin', 'manager']);
    }
}
