<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecordReview extends Model
{
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['created_at' => 'datetime'];

    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewer_id'); }
}
