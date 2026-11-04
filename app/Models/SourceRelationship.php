<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SourceRelationship extends Model
{
    public const TYPES = [
        'INDEPENDENT_REPORTING', 'COMMON_PRIMARY_SOURCE', 'SYNDICATED_REPUBLISHED',
        'SECONDARY_CITATION', 'RELATIONSHIP_UNKNOWN',
    ];

    protected $guarded = [];
    protected $casts = ['reviewed_at' => 'datetime'];

    public function sourceA(): BelongsTo { return $this->belongsTo(Source::class, 'source_a_id'); }
    public function sourceB(): BelongsTo { return $this->belongsTo(Source::class, 'source_b_id'); }
    public function supportingEvidence(): BelongsTo { return $this->belongsTo(EvidenceItem::class, 'supporting_evidence_id'); }
}
