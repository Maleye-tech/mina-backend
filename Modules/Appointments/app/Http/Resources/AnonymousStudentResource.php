<?php

namespace Modules\Appointments\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnonymousStudentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $anonymousId = 'anon_'.substr(hash_hmac('sha256', (string) $this->id, (string) config('app.key')), 0, 16);

        return [
            'id' => $anonymousId,
            'pseudonym' => $this->pseudonym ?: 'Étudiant '.substr($anonymousId, -4),
        ];
    }
}
