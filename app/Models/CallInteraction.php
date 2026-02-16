<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallInteraction extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'call_id',
        'user_id',
        'action',
        'page_seconds',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    const PAGE_SECONDS_CAP = 7200;

    const ACTIONS = [
        'opened',
        'transcribed',
        'skipped',
        'graded',
        'abandoned',
    ];

    const SKIP_REASONS = [
        'not_gradeable'    => 'Not Gradeable',
        'wrong_call_type'  => 'Wrong Call Type',
        'poor_audio'       => 'Poor Audio Quality',
        'not_a_real_call'  => 'Not a Real Call',
        'too_short'        => 'Too Short to Grade',
        'other'            => 'Other',
    ];

    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Cap page_seconds to prevent runaway values from stale tabs.
     */
    public function setPageSecondsAttribute($value)
    {
        $this->attributes['page_seconds'] = $value !== null
            ? min((int) $value, self::PAGE_SECONDS_CAP)
            : null;
    }
}
