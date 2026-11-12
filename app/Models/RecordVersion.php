<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class RecordVersion extends Model
{
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['created_at' => 'datetime'];

    protected static function booted(): void
    {
        // Versi bersifat immutable: perubahan = versi baru.
        static::updating(fn () => throw new LogicException('Versi rekaman bersifat immutable.'));
    }

    public function author(): BelongsTo { return $this->belongsTo(User::class, 'changed_by'); }
}
