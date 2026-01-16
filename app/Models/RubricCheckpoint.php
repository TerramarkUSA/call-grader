<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RubricCheckpoint extends Model
{
    protected $fillable = [
        'name',
        'external_id',
        'description',
        'type',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function checkpointResponses(): HasMany
    {
        return $this->hasMany(GradeCheckpointResponse::class);
    }

    public function isPositive(): bool
    {
        return $this->type === 'positive';
    }

    public function isNegative(): bool
    {
        return $this->type === 'negative';
    }
}
