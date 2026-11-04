<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ThreatActor extends Model
{
    protected $guarded = [];
    protected $casts = [
        'first_seen_at' => 'datetime',
        'is_earliest_in_dataset' => 'boolean',
        'is_simulation' => 'boolean',
        'post_rate' => 'float',
    ];

    public function cluster(): BelongsTo { return $this->belongsTo(NarrativeCluster::class, 'cluster_id'); }
}
