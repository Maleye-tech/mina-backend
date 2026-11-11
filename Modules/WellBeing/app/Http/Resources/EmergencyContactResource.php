<?php

namespace Modules\WellBeing\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\WellBeing\Models\EmergencyContact;

/**
 * @mixin EmergencyContact
 */
class EmergencyContactResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'role_or_description' => $this->role_or_description,
            'phone_number' => $this->phone_number,
            'address' => $this->address,
            'availability' => $this->availability,
            'is_active' => $this->is_active,
        ];
    }
}
