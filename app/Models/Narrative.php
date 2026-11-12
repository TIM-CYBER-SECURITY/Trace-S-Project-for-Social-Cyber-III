<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Narrative extends Model
{
    protected $guarded = [];
    protected $casts = ['captured_at' => 'datetime', 'is_simulation' => 'boolean'];

    public function case(): BelongsTo
    {
        return $this->belongsTo(InvestigationCase::class, 'case_id');
    }

    public function cluster(): BelongsTo
    {
        return $this->belongsTo(NarrativeCluster::class, 'cluster_id');
    }
}
