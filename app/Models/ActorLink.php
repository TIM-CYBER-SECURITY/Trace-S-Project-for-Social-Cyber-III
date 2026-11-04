<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActorLink extends Model
{
    protected $guarded = [];
    protected $casts = ['weight' => 'float'];
}
