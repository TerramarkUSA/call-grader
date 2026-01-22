<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Call extends Model
{
    protected $fillable = [
        'account_id',
        'ctm_activity_id',
        'caller_name',
        'caller_number',
        'talk_time',
        'ring_time',
        'dial_status',
        'source',
        'called_at',
        'salesforce_task_id',
        'rep_id',
        'project_id',
        'call_quality',
        'outcome',
        'call_quality_note',
        'marked_bad_at',
        'marked_bad_by',
        'ignored_at',
        'ignore_reason',
        'processed_at',
        'recording_path',
        'transcript',
        'transcription_quality',
        'speakers_swapped',
        // Salesforce fields
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
    ];

    protected $casts = [
        'called_at' => 'datetime',
        'marked_bad_at' => 'datetime',
        'ignored_at' => 'datetime',
        'processed_at' => 'datetime',
        'transcript' => 'array',
        'speakers_swapped' => 'boolean',
        // Salesforce casts
        'sf_appointment_made' => 'boolean',
        'sf_toured_property' => 'boolean',
        'sf_opportunity_created' => 'boolean',
        'sf_synced_at' => 'datetime',
        'sf_outcome_synced_at' => 'datetime',
    ];

    /**
     * Display status labels
     */
    public const DISPLAY_STATUSES = [
        'conversation' => 'Conversation',
        'short_call' => 'Short Call',
        'no_conversation' => 'No Conversation',
        'voicemail' => 'Voicemail',
        'missed' => 'Missed',
        'abandoned' => 'Abandoned',
        'busy' => 'Busy',
    ];

    /**
     * Grading status labels
     */
    public const GRADING_STATUSES = [
        'needs_processing' => 'Needs Processing',
        'ready' => 'Ready to Grade',
        'in_progress' => 'In Progress',
        'graded' => 'Graded',
    ];

    /**
     * Get the derived display status based on dial_status and talk_time
     */
    public function getDisplayStatusAttribute(): string
    {
        // Map dial_status to display status
        return match ($this->dial_status) {
            'voicemail' => 'voicemail',
            'no_answer', 'missed' => 'missed',
            'abandoned' => 'abandoned',
            'busy' => 'busy',
            'answered', 'completed', 'received' => $this->getAnsweredDisplayStatus(),
            // For any other dial_status, categorize by talk_time
            default => $this->getAnsweredDisplayStatus(),
        };
    }

    /**
     * Get display status for answered calls based on talk_time
     */
    protected function getAnsweredDisplayStatus(): string
    {
        $talkTime = $this->talk_time ?? 0;

        return match (true) {
            $talkTime === 0 => 'abandoned',      // Instant hangup / accidental click
            $talkTime <= 10 => 'no_conversation', // 1-10 seconds
            $talkTime <= 60 => 'short_call',      // 10-60 seconds
            default => 'conversation',            // > 60 seconds
        };
    }

    /**
     * Get the display status label
     */
    public function getDisplayStatusLabelAttribute(): string
    {
        return self::DISPLAY_STATUSES[$this->display_status] ?? 'Unknown';
    }

    /**
     * Get the display status color classes for badges
     */
    public function getDisplayStatusColorAttribute(): string
    {
        return match ($this->display_status) {
            'conversation' => 'bg-green-100 text-green-700',
            'short_call' => 'bg-yellow-100 text-yellow-700',
            'no_conversation' => 'bg-red-100 text-red-700',
            'voicemail' => 'bg-purple-100 text-purple-700',
            'missed' => 'bg-gray-100 text-gray-600',
            'abandoned' => 'bg-orange-100 text-orange-700',
            'busy' => 'bg-gray-100 text-gray-600',
            default => 'bg-gray-100 text-gray-600',
        };
    }

    /**
     * Scope to filter by display status
     */
    public function scopeDisplayStatus($query, string $status)
    {
        // Known dial_status values that map to specific categories
        $answeredStatuses = ['answered', 'completed', 'received'];
        $knownStatuses = array_merge($answeredStatuses, ['voicemail', 'no_answer', 'missed', 'abandoned', 'busy']);

        return match ($status) {
            'conversation' => $query->where('talk_time', '>', 60)
                ->where(function ($q) use ($answeredStatuses, $knownStatuses) {
                    $q->whereIn('dial_status', $answeredStatuses)
                      ->orWhereNotIn('dial_status', $knownStatuses);
                }),
            'short_call' => $query->where('talk_time', '>', 10)->where('talk_time', '<=', 60)
                ->where(function ($q) use ($answeredStatuses, $knownStatuses) {
                    $q->whereIn('dial_status', $answeredStatuses)
                      ->orWhereNotIn('dial_status', $knownStatuses);
                }),
            'no_conversation' => $query->where('talk_time', '>', 0)->where('talk_time', '<=', 10)
                ->where(function ($q) use ($answeredStatuses, $knownStatuses) {
                    $q->whereIn('dial_status', $answeredStatuses)
                      ->orWhereNotIn('dial_status', $knownStatuses);
                }),
            'abandoned' => $query->where(function ($q) use ($answeredStatuses, $knownStatuses) {
                // Abandoned from dial_status OR any status with 0 talk_time (except voicemail/missed/busy)
                $q->where('dial_status', 'abandoned')
                  ->orWhere(function ($q2) use ($answeredStatuses, $knownStatuses) {
                      $q2->where('talk_time', 0)
                         ->where(function ($q3) use ($answeredStatuses, $knownStatuses) {
                             $q3->whereIn('dial_status', $answeredStatuses)
                                ->orWhereNotIn('dial_status', $knownStatuses);
                         });
                  });
            }),
            'voicemail' => $query->where('dial_status', 'voicemail'),
            'missed' => $query->whereIn('dial_status', ['no_answer', 'missed']),
            'busy' => $query->where('dial_status', 'busy'),
            default => $query,
        };
    }

    /**
     * Get the grading status for this call
     * Requires grades relationship to be loaded for efficiency
     */
    public function getGradingStatusAttribute(): string
    {
        // No transcript = needs processing
        if (!$this->transcript) {
            return 'needs_processing';
        }

        // Check grades - use loaded relationship if available
        $grades = $this->relationLoaded('grades') ? $this->grades : $this->grades()->get();

        if ($grades->isEmpty()) {
            return 'ready';
        }

        // Check if any grade is submitted
        if ($grades->where('status', 'submitted')->isNotEmpty()) {
            return 'graded';
        }

        // Has draft grade(s)
        return 'in_progress';
    }

    /**
     * Get the grading status label
     */
    public function getGradingStatusLabelAttribute(): string
    {
        return self::GRADING_STATUSES[$this->grading_status] ?? 'Unknown';
    }

    /**
     * Get the grading status color classes for badges/buttons
     */
    public function getGradingStatusColorAttribute(): string
    {
        return match ($this->grading_status) {
            'needs_processing' => 'bg-blue-600 text-white',
            'ready' => 'bg-blue-100 text-blue-700',
            'in_progress' => 'bg-amber-100 text-amber-700',
            'graded' => 'bg-green-100 text-green-700',
            default => 'bg-gray-100 text-gray-600',
        };
    }

    /**
     * Scope to filter by grading status
     */
    public function scopeGradingStatus($query, string $status)
    {
        return match ($status) {
            'needs_processing' => $query->whereNull('transcript'),
            'ready' => $query->whereNotNull('transcript')
                ->whereDoesntHave('grades'),
            'in_progress' => $query->whereNotNull('transcript')
                ->whereHas('grades', fn($q) => $q->where('status', 'draft'))
                ->whereDoesntHave('grades', fn($q) => $q->where('status', 'submitted')),
            'graded' => $query->whereHas('grades', fn($q) => $q->where('status', 'submitted')),
            default => $query,
        };
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function rep(): BelongsTo
    {
        return $this->belongsTo(Rep::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function markedBadByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_bad_by');
    }

    public function grades(): HasMany
    {
        return $this->hasMany(Grade::class);
    }

    public function coachingNotes(): HasMany
    {
        return $this->hasMany(CoachingNote::class);
    }

    public function objectionTags(): HasMany
    {
        return $this->hasMany(CallObjectionTag::class);
    }

    public function transcriptionLogs(): HasMany
    {
        return $this->hasMany(TranscriptionLog::class);
    }

    public function isInQueue(): bool
    {
        return !$this->ignored_at && !$this->processed_at && $this->call_quality === 'pending';
    }

    public function isBadCall(): bool
    {
        return in_array($this->call_quality, ['voicemail', 'wrong_number', 'no_conversation', 'test', 'spam', 'other']);
    }
}
