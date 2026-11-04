<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConsoleSession extends Model
{
    protected $guarded = [];

    public function messages(): HasMany { return $this->hasMany(ConsoleMessage::class, 'session_id'); }
    public function case(): BelongsTo { return $this->belongsTo(InvestigationCase::class, 'case_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
