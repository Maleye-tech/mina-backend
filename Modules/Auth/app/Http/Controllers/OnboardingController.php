<?php

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\OnboardingQuestion;
use App\Models\University;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $questions = OnboardingQuestion::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['key', 'label', 'input_type', 'is_required', 'sort_order']);

        $universities = University::query()
            ->where('is_partner', true)
            ->with('faculties:id,university_id,name')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'city', 'type', 'website_url', 'sort_order']);

        $user = $request->user();

        return response()->json([
            'questions' => $questions,
            'universities' => $universities,
            'student_profile' => [
                'education_level' => $user->education_level,
                'study_field' => $user->study_field,
                'university_id' => $user->university_id,
                'university' => $user->university,
                'faculty_id' => $user->faculty_id,
                'faculty' => $user->faculty,
                'onboarding_completed' => $user->onboarding_completed_at !== null,
            ],
        ]);
    }
}
