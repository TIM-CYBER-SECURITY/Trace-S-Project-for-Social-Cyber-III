<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Narrative extends Model
{
    protected $guarded = [];
    protected $casts = ['captured_at' => 'datetime', 'is_simulation' => 'boolean'];
}
