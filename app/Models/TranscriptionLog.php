<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TranscriptionLog extends Model
{
    protected $fillable = [
        'call_id',
        'audio_duration_seconds',
        'cost',
        'model',
        'success',
        'error_message',
    ];

    protected $casts = [
        'cost' => 'decimal:4',
        'success' => 'boolean',
    ];

    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class);
    }
}
