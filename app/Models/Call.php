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
    ];

    protected $casts = [
        'called_at' => 'datetime',
        'marked_bad_at' => 'datetime',
        'ignored_at' => 'datetime',
        'processed_at' => 'datetime',
        'transcript' => 'array',
        'speakers_swapped' => 'boolean',
    ];

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
