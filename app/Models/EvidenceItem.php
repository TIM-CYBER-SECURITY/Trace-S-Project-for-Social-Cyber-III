<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvidenceItem extends Model
{
    protected $guarded = [];

    protected $casts = [
        'published_at' => 'datetime',
        'retrieved_at' => 'datetime',
        'engagement' => 'array',
        'is_simulation' => 'boolean',
    ];

    public function case(): BelongsTo { return $this->belongsTo(InvestigationCase::class, 'case_id'); }
    public function source(): BelongsTo { return $this->belongsTo(Source::class); }

    public static function hashOf(?string $content): ?string
    {
        return $content === null || $content === '' ? null : hash('sha256', $content);
    }

    /** Ringkasan engagement yang benar-benar tersedia (tidak mengarang nilai kosong). */
    public function engagementValue(string $key): ?int
    {
        $v = $this->engagement[$key] ?? null;
        return $v === null ? null : (int) $v;
    }
}
