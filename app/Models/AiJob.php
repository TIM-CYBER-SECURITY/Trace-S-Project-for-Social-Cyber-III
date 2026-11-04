<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiJob extends Model
{
    protected $guarded = [];
    protected $casts = ['payload' => 'array', 'finished_at' => 'datetime'];
}
