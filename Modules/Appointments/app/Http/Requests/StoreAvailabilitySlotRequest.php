<?php

namespace Modules\Appointments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Appointments\Enums\SlotMode;
use Modules\Appointments\Models\AvailabilitySlot;

class StoreAvailabilitySlotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'starts_at' => ['required', 'date', 'after:now'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'mode' => ['required', Rule::enum(SlotMode::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'starts_at.required' => 'La date et l’heure de début sont requises.',
            'starts_at.after' => 'Le créneau doit commencer dans le futur.',
            'ends_at.required' => 'L’heure de fin est requise.',
            'ends_at.after' => 'L’heure de fin doit être postérieure à l’heure de début.',
            'mode.required' => 'Le mode de consultation (chat ou visio) est requis.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $userId = $this->user()->id;
            $startsAt = $this->date('starts_at');
            $endsAt = $this->date('ends_at');

            $overlaps = AvailabilitySlot::where('professional_id', $userId)
                ->where(function ($query) use ($startsAt, $endsAt) {
                    $query->where(function ($q) use ($startsAt, $endsAt) {
                        $q->where('starts_at', '<', $endsAt)
                            ->where('ends_at', '>', $startsAt);
                    });
                })
                ->exists();

            if ($overlaps) {
                $v->errors()->add('starts_at', 'Ce créneau chevauche une disponibilité déjà enregistrée.');
            }
        });
    }
}
