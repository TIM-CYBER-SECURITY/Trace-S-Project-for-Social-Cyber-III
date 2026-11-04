<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvidenceComparison extends Model
{
    protected $guarded = [];
    protected $casts = ['diff_json' => 'array', 'is_simulation' => 'boolean', 'reviewed_at' => 'datetime'];

    public function reference(): BelongsTo { return $this->belongsTo(EvidenceItem::class, 'reference_evidence_id'); }
    public function compared(): BelongsTo { return $this->belongsTo(EvidenceItem::class, 'compared_evidence_id'); }
    public function case(): BelongsTo { return $this->belongsTo(InvestigationCase::class, 'case_id'); }
}
