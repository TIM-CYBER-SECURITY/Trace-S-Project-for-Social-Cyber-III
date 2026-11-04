<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiModel extends Model
{
    protected $guarded = [];
    protected $casts = ['is_active' => 'boolean', 'is_simulation' => 'boolean'];

    public function server(): BelongsTo { return $this->belongsTo(AiServer::class, 'server_id'); }
}
