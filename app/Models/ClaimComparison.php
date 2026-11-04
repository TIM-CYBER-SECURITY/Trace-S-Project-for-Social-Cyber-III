<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClaimComparison extends Model
{
    protected $guarded = [];
    protected $casts = ['result_json' => 'array', 'similarity_score' => 'float'];

    public function sourceA(): BelongsTo { return $this->belongsTo(Source::class, 'source_a_id'); }
    public function sourceB(): BelongsTo { return $this->belongsTo(Source::class, 'source_b_id'); }
}
