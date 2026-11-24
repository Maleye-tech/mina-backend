<?php

namespace Modules\Subscriptions\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Subscriptions\Enums\SubscriptionStatus;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'plan_id',
        'partner_institution_id',
        'access_code_id',
        'status',
        'starts_at',
        'ends_at',
        'trial_ends_at',
        'renewed_at',
        'cancelled_at',
        'auto_renew',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'renewed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'auto_renew' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function partnerInstitution(): BelongsTo
    {
        return $this->belongsTo(PartnerInstitution::class, 'partner_institution_id');
    }

    public function accessCode(): BelongsTo
    {
        return $this->belongsTo(AccessCode::class, 'access_code_id');
    }

    public function isValid(): bool
    {
        if ($this->status === SubscriptionStatus::Trial) {
            return $this->trial_ends_at !== null && $this->trial_ends_at->isFuture();
        }

        if ($this->status === SubscriptionStatus::Active) {
            return $this->ends_at !== null && $this->ends_at->isFuture();
        }

        return false;
    }

    public function isTrial(): bool
    {
        return $this->status === SubscriptionStatus::Trial && $this->isValid();
    }

    public function scopeActiveOrTrial(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->where(fn (Builder $sub) => $sub->where('status', SubscriptionStatus::Active->value)->where('ends_at', '>', now()))
                ->orWhere(fn (Builder $sub) => $sub->where('status', SubscriptionStatus::Trial->value)->where('trial_ends_at', '>', now()));
        });
    }
}
