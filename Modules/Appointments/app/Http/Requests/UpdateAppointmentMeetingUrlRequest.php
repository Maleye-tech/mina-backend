<?php

namespace Modules\Appointments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAppointmentMeetingUrlRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'meeting_url' => ['required', 'string', 'url:https', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'meeting_url.required' => 'Le lien de visioconférence est requis.',
            'meeting_url.url' => 'Le lien doit être une URL HTTPS valide.',
        ];
    }
}
