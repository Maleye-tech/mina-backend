<?php

namespace Modules\Appointments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Modules\Appointments\Models\Appointment;
use Modules\Auth\Enums\UserRole;

class CreateUrgentAlertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(UserRole::Student) ?? false;
    }

    public function rules(): array
    {
        return [
            'appointment_id' => ['nullable', 'integer', 'exists:appointments,id'],
            'message' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || ! $this->filled('appointment_id')) {
                return;
            }

            $belongsToStudent = Appointment::query()
                ->whereKey($this->integer('appointment_id'))
                ->where('student_id', $this->user()->id)
                ->exists();

            if (! $belongsToStudent) {
                $validator->errors()->add('appointment_id', 'Ce rendez-vous ne vous appartient pas.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'message.max' => 'Le message ne doit pas dépasser 1000 caractères.',
        ];
    }
}
