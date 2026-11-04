<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NarrativeClusterItem extends Model
{
    protected $guarded = [];
    protected $casts = ['differences' => 'array', 'similarity_score' => 'float'];

    public function cluster(): BelongsTo { return $this->belongsTo(NarrativeCluster::class, 'cluster_id'); }
    public function evidence(): BelongsTo { return $this->belongsTo(EvidenceItem::class, 'evidence_id'); }
}
