<?php

namespace Modules\Auth\Http\Requests;

use App\Models\OnboardingQuestion;

class SubmitStudentOnboardingRequest extends UpdateStudentProfileRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        $activeQuestionKeys = OnboardingQuestion::query()
            ->where('is_active', true)
            ->pluck('key')
            ->all();

        foreach (array_keys($rules) as $key) {
            if (! in_array($key, $activeQuestionKeys, true)) {
                $rules[$key] = ['prohibited'];
            }
        }

        foreach (OnboardingQuestion::query()
            ->where('is_active', true)
            ->where('is_required', true)
            ->pluck('key') as $key) {
            if (array_key_exists($key, $rules)) {
                $rules[$key] = array_values(array_filter(
                    $rules[$key],
                    static fn (string $rule): bool => ! in_array($rule, ['sometimes', 'nullable'], true),
                ));
                array_unshift($rules[$key], 'required');
            }
        }

        return $rules;
    }
}
