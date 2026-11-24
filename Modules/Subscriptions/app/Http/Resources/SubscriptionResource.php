<?php

namespace Modules\Subscriptions\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Subscriptions\Models\Subscription;

/**
 * @mixin Subscription
 */
class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status?->value,
            'status_label' => $this->status?->getLabel(),
            'is_valid' => $this->isValid(),
            'is_trial' => $this->isTrial(),
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'trial_ends_at' => $this->trial_ends_at?->toIso8601String(),
            'auto_renew' => $this->auto_renew,
            'plan' => $this->whenLoaded('plan', fn () => $this->plan ? [
                'id' => $this->plan->id,
                'name' => $this->plan->name,
                'slug' => $this->plan->slug,
                'price' => $this->plan->price,
                'currency' => $this->plan->currency,
            ] : null),
            'partner' => $this->whenLoaded('partnerInstitution', fn () => $this->partnerInstitution ? [
                'id' => $this->partnerInstitution->id,
                'name' => $this->partnerInstitution->name,
                'type' => $this->partnerInstitution->type,
                'covers_mina_care' => $this->partnerInstitution->covers_mina_care,
            ] : null),
        ];
    }
}
