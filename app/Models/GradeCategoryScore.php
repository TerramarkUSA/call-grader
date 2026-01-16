<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GradeCategoryScore extends Model
{
    protected $fillable = [
        'grade_id',
        'rubric_category_id',
        'score',
    ];

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    public function rubricCategory(): BelongsTo
    {
        return $this->belongsTo(RubricCategory::class);
    }
}
