<?php

namespace Modules\Library\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Auth\Enums\UserRole;

class StorePodcastRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(UserRole::Admin) ?? false;
    }

    public function rules(): array
    {
        return [
            'audio' => [
                'required',
                'file',
                'max:'.config('library.max_audio_mb') * 1024,
                'mimetypes:'.implode(',', config('library.accepted_audio_types')),
            ],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'level_id' => ['required', 'integer', 'exists:levels,id'],
            'is_published' => ['sometimes', 'boolean'],
        ];
    }
}
