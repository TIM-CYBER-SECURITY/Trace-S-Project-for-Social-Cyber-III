<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    protected $fillable = ['name', 'code', 'status'];

    public function units(): HasMany { return $this->hasMany(Unit::class); }
    public function users(): HasMany { return $this->hasMany(User::class); }

    public function displayLabel(): string
    {
        return $this->code ? "{$this->name} [{$this->code}]" : (string) $this->name;
    }
}
