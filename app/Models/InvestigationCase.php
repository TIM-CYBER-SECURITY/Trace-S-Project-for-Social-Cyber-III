<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InvestigationCase extends Model
{
    protected $table = 'cases';
    protected $guarded = [];

    protected $casts = ['is_simulation' => 'boolean'];

    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function evidence(): HasMany { return $this->hasMany(EvidenceItem::class, 'case_id'); }
    public function sources(): HasMany { return $this->hasMany(Source::class, 'case_id'); }
    public function analyses(): HasMany { return $this->hasMany(ThreatAnalysis::class, 'case_id'); }
    public function clusters(): HasMany { return $this->hasMany(NarrativeCluster::class, 'case_id'); }
    public function actors(): HasMany { return $this->hasMany(ThreatActor::class, 'case_id'); }
    public function reports(): HasMany { return $this->hasMany(Report::class, 'case_id'); }
    public function comparisons(): HasMany { return $this->hasMany(EvidenceComparison::class, 'case_id'); }
    public function similarities(): HasMany { return $this->hasMany(SimilarityAnalysis::class, 'case_id'); }
    public function claimComparisons(): HasMany { return $this->hasMany(ClaimComparison::class, 'case_id'); }
    public function relationships(): HasMany { return $this->hasMany(SourceRelationship::class, 'case_id'); }

    /** Case ID berikutnya, mis. TS-2026-001. */
    public static function nextCode(): string
    {
        $year = now()->year;
        $prefix = "TS-{$year}-";
        $last = static::where('case_code', 'like', $prefix . '%')->orderByDesc('case_code')->value('case_code');
        $n = $last ? ((int) substr($last, -3)) + 1 : 1;
        return $prefix . str_pad((string) $n, 3, '0', STR_PAD_LEFT);
    }

    /** Evidence ID berikutnya dalam kasus ini, mis. E-001. */
    public function nextEvidenceCode(): string
    {
        $n = $this->evidence()->count() + 1;
        do {
            $code = 'E-' . str_pad((string) $n, 3, '0', STR_PAD_LEFT);
            $n++;
        } while ($this->evidence()->where('evidence_code', $code)->exists());
        return $code;
    }

    public function nextSourceCode(): string
    {
        $n = $this->sources()->count() + 1;
        do {
            $code = 'S-' . str_pad((string) $n, 3, '0', STR_PAD_LEFT);
            $n++;
        } while ($this->sources()->where('source_code', $code)->exists());
        return $code;
    }
}
