<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ThreatAnalysis extends Model
{
    protected $table = 'threat_analyses';
    protected $guarded = [];

    protected $casts = [
        'result' => 'array',
        'is_simulation' => 'boolean',
        'analyzed_at' => 'datetime',
        'confidence' => 'float',
    ];

    public function case(): BelongsTo { return $this->belongsTo(InvestigationCase::class, 'case_id'); }
    public function scanner(): BelongsTo { return $this->belongsTo(User::class, 'scanned_by'); }
    public function reviews(): HasMany { return $this->hasMany(AnalystReview::class, 'analysis_id'); }
}
