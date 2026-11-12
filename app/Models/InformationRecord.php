<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InformationRecord extends Model
{
    use SoftDeletes;

    public const STATUSES = ['DRAFT', 'SUBMITTED', 'UNDER REVIEW', 'APPROVED', 'REVISION REQUIRED', 'REJECTED', 'ARCHIVED'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'archived_at' => 'datetime',
            'is_simulation' => 'boolean',
        ];
    }

    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function organization(): BelongsTo { return $this->belongsTo(Organization::class); }
    public function versions(): HasMany { return $this->hasMany(RecordVersion::class, 'record_id')->orderByDesc('version_number'); }
    public function reviews(): HasMany { return $this->hasMany(RecordReview::class, 'record_id')->orderByDesc('id'); }

    public function version(int $number): ?RecordVersion
    {
        return $this->versions()->where('version_number', $number)->first();
    }
}
