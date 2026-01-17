<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rep extends Model
{
    protected $fillable = [
        'account_id',
        'name',
        'email',
        'sf_user_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function calls(): HasMany
    {
        return $this->hasMany(Call::class);
    }
}
