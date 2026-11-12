<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    protected $fillable = ['name', 'status'];

    public function units(): HasMany { return $this->hasMany(Unit::class); }
    public function users(): HasMany { return $this->hasMany(User::class); }
}
