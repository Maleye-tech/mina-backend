<?php

namespace Modules\Appointments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveSessionNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'min:1', 'max:20000'],
        ];
    }

    public function messages(): array
    {
        return [
            'body.required' => 'Le contenu de la note clinique est requis.',
            'body.max' => 'La note ne doit pas dépasser 20 000 caractères.',
        ];
    }
}
