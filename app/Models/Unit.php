<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Unit extends Model
{
    protected $fillable = ['organization_id', 'name', 'status'];

    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
}
