<?php

namespace Modules\Dashboard\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Appointments\Enums\AppointmentStatus;
use Modules\Appointments\Models\Appointment;
use Modules\Appointments\Models\AppointmentProfessional;

class DashboardController extends Controller
{
    /**
     * Indicateurs clés du tableau de bord selon le rôle connecté.
     *
     * Retourne du JSON uniquement (jamais de view Blade).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $role = $request->string('role');

        $data = match ($role) {
            'professional' => $this->professionalIndicators($user),
            'admin' => $this->adminIndicators(),
            default => $this->defaultIndicators($user),
        };

        return response()->json(['data' => $data]);
    }

    /**
     * Indicateurs pour un professionnel de santé mentale.
     */
    private function professionalIndicators(User $user): array
    {
        $professional = AppointmentProfessional::query()
            ->where('user_id', $user->id)
            ->first();

        $appointments = $professional === null
            ? collect()
            : Appointment::query()->where('professional_id', $professional->id);

        $pendingCount = $appointments->whenNotEmpty(fn ($q) => $q->where('status', AppointmentStatus::Pending))->count();
        $appointmentsCount = $appointments->count();

        return [
            'active_users_count' => DB::table('users')->count(),
            'pending_demands_count' => $pendingCount,
            'appointments_count' => $appointmentsCount,
            'validations_count' => 0,
            'recent_activities' => $appointments
                ->whenNotEmpty(fn ($q) => $q->with('student')->latest('scheduled_at')->limit(5))
                ->get()
                ->map(fn ($a) => [
                    'id' => $a->id,
                    'title' => 'Rendez-vous avec '.($a->student?->name ?? 'Inconnu'),
                    'type' => 'consultation',
                    'date' => $a->scheduled_at?->toIso8601String(),
                    'status' => $a->status->value,
                ])
                ->all(),
        ];
    }

    /**
     * Indicateurs pour un administrateur.
     */
    private function adminIndicators(): array
    {
        return [
            'active_users_count' => DB::table('users')->count(),
            'pending_demands_count' => Appointment::query()->where('status', AppointmentStatus::Pending)->count(),
            'appointments_count' => Appointment::query()->count(),
            'validations_count' => 0,
            'recent_activities' => collect(),
        ];
    }

    /**
     * Indicateurs par défaut (autres rôles).
     */
    private function defaultIndicators(User $user): array
    {
        return [
            'active_users_count' => 0,
            'pending_demands_count' => 0,
            'appointments_count' => 0,
            'validations_count' => 0,
            'recent_activities' => [],
        ];
    }
}
