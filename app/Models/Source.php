<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Source extends Model
{
    protected $guarded = [];

    protected $casts = [
        'published_at' => 'datetime',
        'retrieved_at' => 'datetime',
        'is_simulation' => 'boolean',
    ];

    public function case(): BelongsTo { return $this->belongsTo(InvestigationCase::class, 'case_id'); }
}
