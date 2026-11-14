<?php

namespace App\Models\Concerns;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToOrganizationGroup
{
    /**
     * Auto-assign organization_id on creation from owner/related record or active session user.
     */
    protected static function bootBelongsToOrganizationGroup(): void
    {
        static::creating(function ($model): void {
            if ($model->organization_id !== null) {
                return;
            }

            $orgId = method_exists($model, 'resolveDefaultOrganizationId')
                ? $model->resolveDefaultOrganizationId()
                : null;

            if ($orgId === null && auth()->check()) {
                $orgId = auth()->user()?->organization_id;
            }

            if ($orgId !== null) {
                $model->organization_id = $orgId;
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    /**
     * Scope query to a single Instansi group while keeping unassigned/system records (organization_id = null).
     */
    public function scopeForOrganization(Builder $query, ?int $orgId): Builder
    {
        if (! $orgId) {
            return $query->whereNull('organization_id');
        }

        return $query->where(function (Builder $q) use ($orgId) {
            $q->where('organization_id', $orgId)
                ->orWhereNull('organization_id');
        });
    }
}
