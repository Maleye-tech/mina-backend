<?php

namespace Modules\Subscriptions\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Subscriptions\Models\AccessCode;
use Modules\Subscriptions\Models\SubscriptionPlan;

class PartnerDashboardController extends Controller
{
    /**
     * Tableau de bord institutionnel pour les partenaires (CROUS, universités, ONG).
     */
    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        $institution = $user->partnerInstitution;

        if (! $institution) {
            return response()->json([
                'message' => 'Aucune institution partenaire n\'est encore rattachée à votre compte.',
                'partner' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'position' => $user->partner_position,
                ],
                'institution' => null,
            ], 200);
        }

        $totalCodes = AccessCode::where('partner_institution_id', $institution->id)->count();
        $usedCodes = AccessCode::where('partner_institution_id', $institution->id)->where('is_used', true)->count();
        $availableCodes = $totalCodes - $usedCodes;

        $remainingBeneficiaries = $institution->max_beneficiaries !== null
            ? max(0, $institution->max_beneficiaries - $institution->current_beneficiaries)
            : null;

        return response()->json([
            'partner' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'position' => $user->partner_position,
            ],
            'institution' => [
                'id' => $institution->id,
                'name' => $institution->name,
                'code' => $institution->code,
                'type' => $institution->type,
                'subsidy_rate' => $institution->subsidy_rate,
                'covers_mina_care' => $institution->covers_mina_care,
                'is_active' => $institution->is_active,
                'max_beneficiaries' => $institution->max_beneficiaries,
                'current_beneficiaries' => $institution->current_beneficiaries,
                'remaining_beneficiaries' => $remainingBeneficiaries,
            ],
            'stats' => [
                'total_access_codes' => $totalCodes,
                'used_access_codes' => $usedCodes,
                'available_access_codes' => $availableCodes,
                'active_students' => $institution->current_beneficiaries,
            ],
        ]);
    }

    /**
     * Liste des codes d'accès générés par l'institution.
     */
    public function accessCodes(Request $request): JsonResponse
    {
        $user = $request->user();
        $institution = $user->partnerInstitution;

        if (! $institution) {
            abort(404, 'Aucune institution rattachée.');
        }

        $perPage = min($request->integer('per_page', 20), 100);
        $filter = $request->string('status')->toString(); // 'all', 'used', 'available'

        $query = AccessCode::query()
            ->where('partner_institution_id', $institution->id)
            ->with(['user:id,name,email', 'plan:id,name']);

        if ($filter === 'used') {
            $query->where('is_used', true);
        } elseif ($filter === 'available') {
            $query->where('is_used', false);
        }

        $codes = $query->latest('id')->paginate($perPage);

        return response()->json($codes);
    }

    /**
     * Génération en lot de codes d'accès étudiants pour l'institution.
     */
    public function generateAccessCodes(Request $request): JsonResponse
    {
        $user = $request->user();
        $institution = $user->partnerInstitution;

        if (! $institution) {
            abort(404, 'Aucune institution rattachée.');
        }

        $validated = $request->validate([
            'count' => ['required', 'integer', 'min:1', 'max:100'],
            'duration_days' => ['sometimes', 'integer', 'min:7', 'max:365'],
            'plan_slug' => ['sometimes', 'string', 'exists:subscription_plans,slug'],
        ]);

        $count = $validated['count'];
        $durationDays = $validated['duration_days'] ?? 30;

        $plan = isset($validated['plan_slug'])
            ? SubscriptionPlan::where('slug', $validated['plan_slug'])->first()
            : SubscriptionPlan::first();

        $prefix = strtoupper(substr($institution->code ?: 'MINA', 0, 5));
        $createdCodes = [];

        for ($i = 0; $i < $count; $i++) {
            $uniqueCode = $prefix.'-'.strtoupper(Str::random(6));

            $code = AccessCode::create([
                'code' => $uniqueCode,
                'partner_institution_id' => $institution->id,
                'plan_id' => $plan?->id,
                'duration_days' => $durationDays,
                'is_used' => false,
                'expires_at' => now()->addDays(90),
            ]);

            $createdCodes[] = $code->code;
        }

        return response()->json([
            'message' => "{$count} code(s) d'accès étudiant(s) généré(s) avec succès.",
            'codes' => $createdCodes,
            'duration_days' => $durationDays,
            'expires_at' => now()->addDays(90)->toDateString(),
        ], 201);
    }
}
