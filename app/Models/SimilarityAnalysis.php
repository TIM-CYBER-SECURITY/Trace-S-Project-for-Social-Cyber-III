<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SimilarityAnalysis extends Model
{
    protected $guarded = [];
    protected $casts = ['result_json' => 'array', 'is_simulation' => 'boolean', 'analyzed_at' => 'datetime', 'score' => 'float'];

    public function evidenceA(): BelongsTo { return $this->belongsTo(EvidenceItem::class, 'evidence_a_id'); }
    public function evidenceB(): BelongsTo { return $this->belongsTo(EvidenceItem::class, 'evidence_b_id'); }
}
