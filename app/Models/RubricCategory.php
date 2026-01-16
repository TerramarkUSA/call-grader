<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RubricCategory extends Model
{
    protected $fillable = [
        'name',
        'external_id',
        'description',
        'weight',
        'scoring_criteria',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'weight' => 'decimal:2',
        'scoring_criteria' => 'array',
        'is_active' => 'boolean',
    ];

    public function categoryScores(): HasMany
    {
        return $this->hasMany(GradeCategoryScore::class);
    }

    public function coachingNotes(): HasMany
    {
        return $this->hasMany(CoachingNote::class);
    }
}
