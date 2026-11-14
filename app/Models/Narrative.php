<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganizationGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Narrative extends Model
{
    use BelongsToOrganizationGroup;

    protected $guarded = [];
    protected $casts = ['captured_at' => 'datetime', 'is_simulation' => 'boolean'];

    protected function resolveDefaultOrganizationId(): ?int
    {
        return $this->case_id ? InvestigationCase::find($this->case_id)?->organization_id : null;
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(InvestigationCase::class, 'case_id');
    }

    public function cluster(): BelongsTo
    {
        return $this->belongsTo(NarrativeCluster::class, 'cluster_id');
    }
}
