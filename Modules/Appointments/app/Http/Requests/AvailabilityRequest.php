<?php

namespace Modules\Appointments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $rules = [
            'weekday' => ['sometimes', 'required', 'integer', 'min:0', 'max:6'],
            'start_time' => ['sometimes', 'required', 'date_format:H:i'],
            'end_time' => ['sometimes', 'required', 'date_format:H:i'],
            'is_active' => ['sometimes', 'required', 'boolean'],
        ];

        if ($this->isMethod('post')) {
            $rules['weekday'] = ['required', 'integer', 'min:0', 'max:6'];
            $rules['start_time'] = ['required', 'date_format:H:i'];
            $rules['end_time'] = ['required', 'date_format:H:i', 'after:start_time'];
        }

        return $rules;
    }
}
