<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GradeCheckpointResponse extends Model
{
    protected $fillable = [
        'grade_id',
        'rubric_checkpoint_id',
        'observed',
    ];

    protected $casts = [
        'observed' => 'boolean',
    ];

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    public function rubricCheckpoint(): BelongsTo
    {
        return $this->belongsTo(RubricCheckpoint::class);
    }
}
