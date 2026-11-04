<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiModelChange extends Model
{
    protected $guarded = [];

    public function model(): BelongsTo { return $this->belongsTo(AiModel::class, 'model_id'); }
    public function admin(): BelongsTo { return $this->belongsTo(User::class, 'changed_by'); }
}
