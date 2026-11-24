<?php

namespace Modules\Subscriptions\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AccessCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'partner_institution_id',
        'user_id',
        'plan_id',
        'duration_days',
        'is_used',
        'used_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'duration_days' => 'integer',
            'is_used' => 'boolean',
            'used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function partnerInstitution(): BelongsTo
    {
        return $this->belongsTo(PartnerInstitution::class, 'partner_institution_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class, 'access_code_id');
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('is_used', false)
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', now()));
    }

    public function isValid(): bool
    {
        if ($this->is_used) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->partnerInstitution && ! $this->partnerInstitution->canAcceptNewBeneficiary()) {
            return false;
        }

        return true;
    }
}
