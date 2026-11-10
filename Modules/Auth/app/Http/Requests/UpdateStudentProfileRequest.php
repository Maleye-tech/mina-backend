<?php

namespace Modules\Auth\Http\Requests;

use App\Models\Faculty;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Modules\Auth\Enums\UserRole;

class UpdateStudentProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(UserRole::Student) ?? false;
    }

    public function rules(): array
    {
        return [
            'education_level' => ['sometimes', 'nullable', 'string', 'max:255'],
            'study_field' => ['sometimes', 'nullable', 'string', 'max:255'],
            'university_id' => ['sometimes', 'nullable', 'integer', 'exists:universities,id'],
            'faculty_id' => ['sometimes', 'nullable', 'integer', 'exists:faculties,id'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['university_id', 'faculty_id'])) {
                    return;
                }

                $universityId = $this->input('university_id', $this->user()->university_id);
                $facultyId = $this->input('faculty_id', $this->user()->faculty_id);

                if ($facultyId === null) {
                    return;
                }

                if ($universityId === null) {
                    $validator->errors()->add('university_id', 'Une université doit être sélectionnée pour cette faculté.');

                    return;
                }

                if (! Faculty::query()
                    ->whereKey($facultyId)
                    ->where('university_id', $universityId)
                    ->exists()) {
                    $validator->errors()->add('faculty_id', 'La faculté sélectionnée ne dépend pas de cette université.');
                }
            },
        ];
    }
}
