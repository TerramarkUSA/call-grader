<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Grade extends Model
{
    protected $fillable = [
        'call_id',
        'graded_by',
        'overall_score',
        'appointment_quality',
        'no_appointment_reasons',
        'playback_seconds',
        'grading_started_at',
        'grading_completed_at',
        'status',
    ];

    protected $casts = [
        'overall_score' => 'decimal:2',
        'grading_started_at' => 'datetime',
        'grading_completed_at' => 'datetime',
        'no_appointment_reasons' => 'array',
    ];

    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class);
    }

    public function gradedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }

    public function categoryScores(): HasMany
    {
        return $this->hasMany(GradeCategoryScore::class);
    }

    public function checkpointResponses(): HasMany
    {
        return $this->hasMany(GradeCheckpointResponse::class);
    }

    public function coachingNotes(): HasMany
    {
        return $this->hasMany(CoachingNote::class);
    }

    public function calculateOverallScore(): float
    {
        $totalWeight = 0;
        $weightedSum = 0;

        foreach ($this->categoryScores as $categoryScore) {
            if ($categoryScore->score !== null) {
                $weight = $categoryScore->rubricCategory->weight;
                $weightedSum += $categoryScore->score * $weight;
                $totalWeight += $weight;
            }
        }

        return $totalWeight > 0 ? round($weightedSum / $totalWeight, 2) : 0;
    }

    public function getGradingDurationSeconds(): ?int
    {
        if ($this->grading_started_at && $this->grading_completed_at) {
            return $this->grading_completed_at->diffInSeconds($this->grading_started_at);
        }
        return null;
    }

    public function getPlaybackRatio(): ?float
    {
        $callDuration = $this->call->talk_time;
        if ($callDuration > 0 && $this->playback_seconds > 0) {
            return round(($this->playback_seconds / $callDuration) * 100, 1);
        }
        return null;
    }

    public function isSubmitted(): bool
    {
        return $this->status === 'submitted';
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }
}
