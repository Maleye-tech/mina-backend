<?php

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\OnboardingQuestion;
use App\Models\University;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Subscriptions\Models\PartnerInstitution;

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

        $partnerInstitutions = PartnerInstitution::query()
            ->active()
            ->get(['id', 'name', 'type', 'code']);

        $roles = [
            [
                'id' => 'student',
                'title' => 'Étudiant',
                'subtitle' => 'Médecine, pharmacie, odontologie ou filière santé',
                'icon' => 'graduation-cap',
            ],
            [
                'id' => 'ambassador',
                'title' => 'Ambassadeur',
                'subtitle' => 'Étudiant relais formé à l’écoute',
                'icon' => 'heart-handshake',
            ],
            [
                'id' => 'partner',
                'title' => 'Partenaire',
                'subtitle' => 'Université, CROUS, ONG ou institution',
                'icon' => 'building-columns',
            ],
        ];

        $user = $request->user();

        return response()->json([
            'available_roles' => $roles,
            'questions' => $questions,
            'universities' => $universities,
            'partner_institutions' => $partnerInstitutions,
            'current_role' => $user?->role?->value,
            'student_profile' => $user ? [
                'education_level' => $user->education_level,
                'study_field' => $user->study_field,
                'university_id' => $user->university_id,
                'university' => $user->university,
                'faculty_id' => $user->faculty_id,
                'faculty' => $user->faculty,
                'onboarding_completed' => $user->onboarding_completed_at !== null,
            ] : null,
        ]);
    }
}
