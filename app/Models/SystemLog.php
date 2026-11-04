<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Audit log append-only: tidak boleh diubah atau dihapus lewat aplikasi
 * (Supplemental §3.4: administrator tidak boleh mengubah/menghapus secara diam-diam).
 */
class SystemLog extends Model
{
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['metadata' => 'array', 'created_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit log bersifat append-only.'));
        static::deleting(fn () => throw new LogicException('Audit log bersifat append-only.'));
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
