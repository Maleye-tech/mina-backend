<?php

namespace Modules\Subscriptions\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartnerInstitution extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'code',
        'max_beneficiaries',
        'current_beneficiaries',
        'subsidy_rate',
        'covers_mina_care',
        'is_active',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'max_beneficiaries' => 'integer',
            'current_beneficiaries' => 'integer',
            'subsidy_rate' => 'integer',
            'covers_mina_care' => 'boolean',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function accessCodes(): HasMany
    {
        return $this->hasMany(AccessCode::class, 'partner_institution_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'partner_institution_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }

    public function canAcceptNewBeneficiary(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->ends_at && $this->ends_at->isPast()) {
            return false;
        }

        if ($this->max_beneficiaries !== null && $this->current_beneficiaries >= $this->max_beneficiaries) {
            return false;
        }

        return true;
    }
}
