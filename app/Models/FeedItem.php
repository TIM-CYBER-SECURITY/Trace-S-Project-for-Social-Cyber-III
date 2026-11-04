<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeedItem extends Model
{
    protected $guarded = [];
    protected $casts = ['published_at' => 'datetime'];

    public function feed(): BelongsTo { return $this->belongsTo(RssFeed::class, 'feed_id'); }
}
