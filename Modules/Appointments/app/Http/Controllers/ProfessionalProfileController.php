<?php

namespace Modules\Appointments\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Appointments\Http\Requests\AcceptCharterRequest;
use Modules\Appointments\Http\Requests\UpdateProfessionalProfileRequest;
use Modules\Appointments\Http\Resources\ProfessionalProfileResource;
use Modules\Appointments\Models\ProfessionalProfile;
use Modules\Appointments\Services\ProfessionalProfileService;

class ProfessionalProfileController extends Controller
{
    public function __construct(private readonly ProfessionalProfileService $profiles) {}

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        $profile = DB::transaction(function () use ($user): ProfessionalProfile {
            $profile = ProfessionalProfile::query()->firstOrCreate(
                ['user_id' => $user->id],
                [
                    'display_name' => $user->name,
                    'specialty' => 'psychologue',
                    'languages' => ['Français'],
                    'is_verified' => false,
                ],
            );

            $this->profiles->syncLegacyProfile($profile);

            return $profile;
        });

        return response()->json([
            'profile' => ProfessionalProfileResource::make($profile),
        ]);
    }

    public function update(UpdateProfessionalProfileRequest $request): JsonResponse
    {
        $user = $request->user();

        $profile = DB::transaction(function () use ($request, $user): ProfessionalProfile {
            $profile = ProfessionalProfile::query()->firstOrCreate(
                ['user_id' => $user->id],
                [
                    'display_name' => $user->name,
                    'specialty' => 'psychologue',
                    'languages' => ['Français'],
                    'is_verified' => false,
                ],
            );

            $profile->update($request->validated());
            $this->profiles->syncLegacyProfile($profile);

            return $profile;
        });

        return response()->json([
            'message' => 'Profil professionnel mis à jour avec succès.',
            'profile' => ProfessionalProfileResource::make($profile),
        ]);
    }

    public function acceptCharter(AcceptCharterRequest $request): JsonResponse
    {
        $user = $request->user();

        $profile = DB::transaction(function () use ($user): ProfessionalProfile {
            $profile = ProfessionalProfile::query()->firstOrCreate(
                ['user_id' => $user->id],
                [
                    'display_name' => $user->name,
                    'specialty' => 'psychologue',
                    'languages' => ['Français'],
                    'is_verified' => false,
                ],
            );

            $profile->update(['charter_accepted_at' => now()]);
            $this->profiles->syncLegacyProfile($profile);

            return $profile;
        });

        return response()->json([
            'message' => 'La charte de confidentialité et de déontologie a été acceptée avec succès.',
            'profile' => ProfessionalProfileResource::make($profile),
        ]);
    }
}
