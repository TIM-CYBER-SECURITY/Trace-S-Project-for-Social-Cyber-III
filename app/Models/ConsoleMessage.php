<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConsoleMessage extends Model
{
    protected $guarded = [];
    protected $casts = ['sources' => 'array', 'tool_calls' => 'array', 'is_simulation' => 'boolean'];
}
