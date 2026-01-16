<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallObjectionTag extends Model
{
    protected $fillable = [
        'call_id',
        'objection_type_id',
        'tagged_by',
    ];

    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class);
    }

    public function objectionType(): BelongsTo
    {
        return $this->belongsTo(ObjectionType::class);
    }

    public function taggedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tagged_by');
    }
}
