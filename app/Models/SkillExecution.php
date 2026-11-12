<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SkillExecution extends Model
{
    public $timestamps = false;
    protected $guarded = [];

    protected function casts(): array
    {
        return ['input' => 'array', 'result' => 'array', 'created_at' => 'datetime'];
    }

    public function skill(): BelongsTo { return $this->belongsTo(Skill::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
