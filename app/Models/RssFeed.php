<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RssFeed extends Model
{
    protected $guarded = [];
    protected $casts = ['is_active' => 'boolean', 'last_fetched_at' => 'datetime'];

    public function items(): HasMany { return $this->hasMany(FeedItem::class, 'feed_id'); }
}
