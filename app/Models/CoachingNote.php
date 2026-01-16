<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoachingNote extends Model
{
    protected $fillable = [
        'grade_id',
        'call_id',
        'author_id',
        'line_index_start',
        'line_index_end',
        'timestamp_start',
        'timestamp_end',
        'transcript_text',
        'note_text',
        'rubric_category_id',
        'is_objection',
        'objection_type_id',
        'objection_outcome',
        'is_exemplar',
    ];

    protected $casts = [
        'is_objection' => 'boolean',
        'is_exemplar' => 'boolean',
    ];

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function rubricCategory(): BelongsTo
    {
        return $this->belongsTo(RubricCategory::class);
    }

    // Alias for rubricCategory for convenience
    public function category(): BelongsTo
    {
        return $this->belongsTo(RubricCategory::class, 'rubric_category_id');
    }

    public function objectionType(): BelongsTo
    {
        return $this->belongsTo(ObjectionType::class);
    }
}
