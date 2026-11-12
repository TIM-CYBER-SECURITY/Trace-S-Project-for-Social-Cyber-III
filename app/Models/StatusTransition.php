<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/** Riwayat transisi status; append-only di level aplikasi. */
class StatusTransition extends Model
{
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['created_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Riwayat transisi bersifat append-only.'));
        static::deleting(fn () => throw new LogicException('Riwayat transisi bersifat append-only.'));
    }
}
