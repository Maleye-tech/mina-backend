<?php

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\OnboardingQuestion;
use Illuminate\Http\JsonResponse;
use Modules\Auth\Http\Requests\UpdateOnboardingQuestionRequest;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class OnboardingQuestionController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'questions' => OnboardingQuestion::query()
                ->orderBy('sort_order')
                ->get(['key', 'label', 'input_type', 'is_required', 'is_active', 'sort_order']),
        ]);
    }

    public function update(UpdateOnboardingQuestionRequest $request, string $key): JsonResponse
    {
        $question = OnboardingQuestion::query()->where('key', $key)->first();

        if (! $question) {
            throw new NotFoundHttpException('Cette question d’onboarding est introuvable.');
        }

        $question->fill($request->validated())->save();

        return response()->json([
            'question' => $question->only([
                'key',
                'label',
                'input_type',
                'is_required',
                'is_active',
                'sort_order',
            ]),
        ]);
    }
}
