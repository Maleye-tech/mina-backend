<?php

namespace App\Http\Controllers;

use App\Models\MentalHealthAuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Appointments\Models\Appointment;
use Modules\Assessment\Models\Attempt;
use Modules\Payments\Models\Payment;
use Modules\Subscriptions\Models\Subscription;
use Modules\WellBeing\Models\UserTestResult;

class PrivacyController extends Controller
{
    /**
     * Droit d'accès et d'export des données personnelles et académiques (CDP Sénégal).
     */
    public function export(Request $request): JsonResponse
    {
        $user = $request->user();

        // Log audit for sensitive health data export
        MentalHealthAuditLog::log(
            accessor: $user,
            student: $user,
            resourceType: 'full_export',
            resourceId: $user->id,
            action: 'export',
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        $data = [
            'profil' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'pseudonym' => $user->pseudonym,
                'education_level' => $user->education_level,
                'study_field' => $user->study_field,
                'university' => $user->university,
                'faculty' => $user->faculty,
                'created_at' => $user->created_at->toIso8601String(),
            ],
            'consentements_cdp' => $user->consents()->get(['consent_type', 'granted', 'version', 'created_at']),
            'sessions_qcm' => Attempt::query()->forUser($user)->completed()->get(['id', 'score', 'correct_count', 'total_questions', 'completed_at']),
            'tests_bien_etre' => UserTestResult::query()->where('user_id', $user->id)->get(['id', 'test_id', 'total_score', 'severity_level', 'completed_at']),
            'rendez_vous_care' => Appointment::query()->where('student_id', $user->id)->get(['id', 'scheduled_at', 'duration_minutes', 'status', 'consultation_type']),
            'abonnements' => Subscription::query()->where('user_id', $user->id)->get(['id', 'status', 'starts_at', 'ends_at', 'trial_ends_at']),
            'historique_paiements' => Payment::query()->where('user_id', $user->id)->get(['id', 'reference', 'amount', 'currency', 'status', 'paid_at']),
        ];

        return response()->json([
            'declaration_cdp' => 'Export des données conforme à la loi n° 2008-12 sur la protection des données à caractère personnel (CDP Sénégal).',
            'exported_at' => now()->toIso8601String(),
            'donnees' => $data,
        ]);
    }

    /**
     * Droit à l'effacement et suppression du compte (CDP Sénégal).
     */
    public function deleteAccount(Request $request): JsonResponse
    {
        $request->validate([
            'confirmation' => ['required', 'string', 'in:SUPPRIMER'],
        ]);

        $user = $request->user();

        DB::transaction(function () use ($user, $request): void {
            MentalHealthAuditLog::log(
                accessor: $user,
                student: $user,
                resourceType: 'user_account',
                resourceId: $user->id,
                action: 'delete',
                ip: $request->ip(),
                userAgent: $request->userAgent()
            );

            // Anonymize/delete user tokens and personal data
            $user->tokens()->delete();
            $user->delete();
        });

        return response()->json([
            'message' => 'Votre compte et l\'ensemble de vos données associées ont été supprimés avec succès conformément à vos droits.',
        ]);
    }
}
