<?php

namespace Modules\Appointments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PrivateNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'appointment_id' => ['nullable', 'integer', 'exists:appointments,id'],
            'conversation_id' => ['nullable', 'integer', 'exists:chat_conversations,id'],
            'body' => ['required', 'string', 'max:20000'],
        ];
    }

    public function messages(): array
    {
        return [
            'body.max' => 'La note ne doit pas dépasser 20 000 caractères.',
        ];
    }
}
