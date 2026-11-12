<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Skill extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['input_schema' => 'array', 'configuration' => 'array'];
    }

    public function executions(): HasMany { return $this->hasMany(SkillExecution::class); }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_skill_permissions')->withPivot(['enabled', 'assigned_by', 'assigned_at']);
    }

    public function isUsable(): bool
    {
        return $this->installation_status === 'INSTALLED' && $this->status === 'ENABLED';
    }
}
