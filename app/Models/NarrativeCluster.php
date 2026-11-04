<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NarrativeCluster extends Model
{
    protected $guarded = [];
    protected $casts = ['earliest_observed_at' => 'datetime', 'is_simulation' => 'boolean'];

    public function case(): BelongsTo { return $this->belongsTo(InvestigationCase::class, 'case_id'); }
    public function items(): HasMany { return $this->hasMany(NarrativeClusterItem::class, 'cluster_id'); }
    public function actors(): HasMany { return $this->hasMany(ThreatActor::class, 'cluster_id'); }
}
