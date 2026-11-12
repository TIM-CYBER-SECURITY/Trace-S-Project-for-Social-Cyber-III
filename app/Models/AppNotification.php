<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppNotification extends Model
{
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['read_at' => 'datetime', 'created_at' => 'datetime'];
}
