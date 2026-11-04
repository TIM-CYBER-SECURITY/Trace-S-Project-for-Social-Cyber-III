<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalystReview extends Model
{
    protected $guarded = [];
    protected $casts = ['previous_result' => 'array', 'final_result' => 'array'];

    public function analysis(): BelongsTo { return $this->belongsTo(ThreatAnalysis::class, 'analysis_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
