<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganizationGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConsoleSession extends Model
{
    use BelongsToOrganizationGroup;

    protected $guarded = [];

    public function messages(): HasMany { return $this->hasMany(ConsoleMessage::class, 'session_id'); }
    public function case(): BelongsTo { return $this->belongsTo(InvestigationCase::class, 'case_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    protected function resolveDefaultOrganizationId(): ?int
    {
        return $this->user_id ? User::find($this->user_id)?->organization_id : null;
    }
}
