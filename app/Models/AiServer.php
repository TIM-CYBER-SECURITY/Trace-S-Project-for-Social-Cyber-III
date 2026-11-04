<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiServer extends Model
{
    protected $guarded = [];
    protected $casts = ['is_active' => 'boolean', 'last_checked_at' => 'datetime'];

    public function models(): HasMany { return $this->hasMany(AiModel::class, 'server_id'); }
}
