<?php

namespace Modules\Chat\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Auth\Enums\UserRole;

class StoreConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'participant_id' => [
                'required',
                'integer',
                Rule::notIn([$this->user()?->id]),
                'exists:users,id',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'participant_id.required' => 'Vous devez indiquer un participant.',
            'participant_id.not_in' => 'Vous ne pouvez pas démarrer une conversation avec vous-même.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $participantId = $this->integer('participant_id');

            if ($participantId === 0 || $validator->errors()->isNotEmpty()) {
                return;
            }

            $participant = User::find($participantId);

            if ($participant === null) {
                return;
            }

            $roles = [$this->user()->role, $participant->role];

            // Une conversation relie un étudiant et un professionnel de santé mentale.
            $isAllowedPair = in_array(UserRole::Student, $roles, true)
                && in_array(UserRole::Professional, $roles, true);

            if (! $isAllowedPair) {
                $validator->errors()->add(
                    'participant_id',
                    'Seules les conversations entre un étudiant et un professionnel de santé mentale sont autorisées.',
                );
            }
        });
    }
}
