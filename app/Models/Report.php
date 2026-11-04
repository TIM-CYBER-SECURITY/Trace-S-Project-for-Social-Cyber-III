<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    protected $guarded = [];
    protected $casts = ['content' => 'array', 'is_simulation' => 'boolean'];

    public function case(): BelongsTo { return $this->belongsTo(InvestigationCase::class, 'case_id'); }
    public function author(): BelongsTo { return $this->belongsTo(User::class, 'generated_by'); }
}
