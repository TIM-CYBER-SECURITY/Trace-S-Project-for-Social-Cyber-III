<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Connector extends Model
{
    protected $guarded = [];
    protected $casts = ['last_run_at' => 'datetime'];
}
