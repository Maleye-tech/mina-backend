<?php

namespace Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Faculty;
use App\Models\University;
use App\Models\User;
use App\Models\UserConsent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Auth\Enums\UserRole;
use Modules\Auth\Http\Requests\ChangePasswordRequest;
use Modules\Auth\Http\Requests\DeleteAccountRequest;
use Modules\Auth\Http\Requests\ForgotPasswordRequest;
use Modules\Auth\Http\Requests\LoginRequest;
use Modules\Auth\Http\Requests\RegisterRequest;
use Modules\Auth\Http\Requests\ResetPasswordRequest;
use Modules\Auth\Http\Requests\SubmitStudentOnboardingRequest;
use Modules\Auth\Http\Requests\UpdateStudentProfileRequest;
use Modules\Auth\Http\Requests\UpdateUserSettingsRequest;
use Modules\Auth\Http\Requests\VerifyTwoFactorCodeRequest;
use PragmaRX\Google2FA\Google2FA;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        // L'inscription publique crée toujours un étudiant.
        // Les ambassadeurs et professionnels sont créés par un administrateur.
        $user = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
            'role' => UserRole::Student,
        ]);

        $user->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Compte créé. Vérifiez votre adresse e-mail pour la confirmer.',
            'user' => $this->present($user),
            'token' => $user->createToken('mina-app', ['full-access'])->plainTextToken,
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Identifiants incorrects.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Ce compte est désactivé.'],
            ]);
        }

        if ($user->hasRole(UserRole::Admin)) {
            $tokenName = $user->two_factor_enabled_at
                ? 'admin-2fa-challenge'
                : 'admin-2fa-setup';
            $ability = $user->two_factor_enabled_at
                ? 'two-factor-challenge'
                : 'two-factor-setup';
            $token = $user->createToken($tokenName, [$ability], now()->addMinutes(10));

            return response()->json([
                'message' => $user->two_factor_enabled_at
                    ? 'Saisissez le code de votre application d’authentification.'
                    : 'Configurez la double authentification avant de continuer.',
                'requires_two_factor' => (bool) $user->two_factor_enabled_at,
                'requires_two_factor_setup' => ! $user->two_factor_enabled_at,
                'challenge_token' => $token->plainTextToken,
            ]);
        }

        return response()->json([
            'message' => 'Connexion réussie.',
            'user' => $this->present($user),
            'token' => $user->createToken('mina-app', ['full-access'])->plainTextToken,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Déconnexion réussie.']);
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Toutes les sessions ont été déconnectées.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->present($request->user())]);
    }

    public function updateSettings(UpdateUserSettingsRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        DB::transaction(function () use ($user, $data): void {
            $user->fill(array_intersect_key($data, array_flip([
                'pseudonym',
                'preferred_language',
                'notifications_enabled',
            ])))->save();

            foreach ($data['consents'] ?? [] as $consentType => $granted) {
                $user->consents()->create([
                    'consent_type' => $consentType,
                    'granted' => $granted,
                    'version' => config("privacy.consents.{$consentType}.version"),
                ]);
            }
        });

        return response()->json([
            'message' => 'Vos préférences et consentements ont été enregistrés.',
            'user' => $this->present($user->fresh()),
        ]);
    }

    public function updateStudentProfile(UpdateStudentProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $this->saveStudentProfile($user, $request->validated());

        return response()->json([
            'message' => 'Le profil étudiant a été mis à jour.',
            'user' => $this->present($user),
        ]);
    }

    public function completeStudentOnboarding(SubmitStudentOnboardingRequest $request): JsonResponse
    {
        $user = $request->user();
        $this->saveStudentProfile($user, $request->validated(), complete: true);

        return response()->json([
            'message' => 'Votre onboarding est terminé.',
            'user' => $this->present($user),
        ]);
    }

    public function setupTwoFactor(Request $request, Google2FA $google2fa): JsonResponse
    {
        $user = $request->user();

        abort_if($user->two_factor_enabled_at, 409, 'La double authentification est déjà activée.');

        if (! $user->two_factor_secret) {
            $user->forceFill([
                'two_factor_secret' => $google2fa->generateSecretKey(),
            ])->save();
        }

        return response()->json([
            'secret' => $user->two_factor_secret,
            'otpauth_url' => $google2fa->getQRCodeUrl(
                config('app.name'),
                $user->email,
                $user->two_factor_secret,
            ),
        ]);
    }

    public function confirmTwoFactorSetup(
        VerifyTwoFactorCodeRequest $request,
        Google2FA $google2fa,
    ): JsonResponse {
        $userId = $request->user()->getKey();
        $challengeToken = $request->user()->currentAccessToken();

        $token = DB::transaction(function () use ($request, $google2fa, $userId, $challengeToken): string {
            $user = User::query()->lockForUpdate()->findOrFail($userId);

            abort_if($user->two_factor_enabled_at, 409, 'La double authentification est déjà activée.');

            $counter = $google2fa->verifyKeyNewer(
                $user->two_factor_secret,
                $request->validated('code'),
                $user->two_factor_last_used_counter ?? 0,
            );

            if ($counter === false) {
                throw ValidationException::withMessages([
                    'code' => ['Le code de vérification est invalide ou a expiré.'],
                ]);
            }

            $user->forceFill([
                'two_factor_enabled_at' => now(),
                'two_factor_last_used_counter' => $counter,
            ])->save();

            $challengeToken->delete();

            return $user->createToken('mina-app', ['full-access'])->plainTextToken;
        });

        return response()->json([
            'message' => 'La double authentification est activée.',
            'token' => $token,
        ]);
    }

    public function completeTwoFactorChallenge(
        VerifyTwoFactorCodeRequest $request,
        Google2FA $google2fa,
    ): JsonResponse {
        $userId = $request->user()->getKey();
        $challengeToken = $request->user()->currentAccessToken();

        $token = DB::transaction(function () use ($request, $google2fa, $userId, $challengeToken): string {
            $user = User::query()->lockForUpdate()->findOrFail($userId);

            abort_unless($user->two_factor_enabled_at && $user->two_factor_secret, 403);

            $counter = $google2fa->verifyKeyNewer(
                $user->two_factor_secret,
                $request->validated('code'),
                $user->two_factor_last_used_counter ?? 0,
            );

            if ($counter === false) {
                throw ValidationException::withMessages([
                    'code' => ['Le code de vérification est invalide ou a déjà été utilisé.'],
                ]);
            }

            $user->forceFill([
                'two_factor_last_used_counter' => $counter,
            ])->save();

            $challengeToken->delete();

            return $user->createToken('mina-app', ['full-access'])->plainTextToken;
        });

        return response()->json([
            'message' => 'Connexion réussie.',
            'token' => $token,
        ]);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $this->ensureCurrentPassword($user, $request->validated('current_password'));

        $user->forceFill([
            'password' => $request->validated('password'),
            'remember_token' => Str::random(60),
        ])->save();
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Votre mot de passe a été modifié. Veuillez vous reconnecter.',
        ]);
    }

    public function deleteAccount(DeleteAccountRequest $request): JsonResponse
    {
        $user = $request->user();
        $this->ensureCurrentPassword($user, $request->validated('current_password'));

        DB::transaction(function () use ($user): void {
            $user->tokens()->delete();
            $user->delete();
        });

        return response()->json([
            'message' => 'Votre compte a été supprimé.',
        ]);
    }

    public function verifyEmail(string $id, string $hash): JsonResponse
    {
        $user = User::query()->findOrFail($id);

        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            abort(403, 'Le lien de vérification est invalide.');
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return response()->json([
            'message' => 'Votre adresse e-mail a été vérifiée.',
        ]);
    }

    public function resendVerificationEmail(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Votre adresse e-mail est déjà vérifiée.',
            ]);
        }

        $user->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Un nouveau lien de vérification a été envoyé.',
        ], 202);
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        Password::sendResetLink($request->validated());

        return response()->json([
            'message' => 'Si un compte correspond à cette adresse, un lien de réinitialisation sera envoyé.',
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->safe()->only(['email', 'token', 'password']),
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                $user->tokens()->delete();
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'token' => ['Le lien de réinitialisation est invalide ou a expiré.'],
            ]);
        }

        return response()->json([
            'message' => 'Votre mot de passe a été réinitialisé avec succès.',
        ]);
    }

    private function present(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified' => $user->hasVerifiedEmail(),
            'role' => $user->role->value,
            'role_label' => $user->role->label(),
            'pseudonym' => $user->pseudonym,
            'preferences' => [
                'language' => $user->preferred_language,
                'notifications_enabled' => $user->notifications_enabled,
            ],
            'consents' => [
                UserConsent::PERSONAL_DATA => $user->consentStatus(UserConsent::PERSONAL_DATA),
                UserConsent::MENTAL_HEALTH_DATA => $user->consentStatus(UserConsent::MENTAL_HEALTH_DATA),
            ],
            'student_profile' => $user->hasRole(UserRole::Student) ? [
                'education_level' => $user->education_level,
                'study_field' => $user->study_field,
                'university_id' => $user->university_id,
                'faculty' => $user->faculty,
                'faculty_id' => $user->faculty_id,
                'university' => $user->university,
                'onboarding_completed' => $user->onboarding_completed_at !== null,
            ] : null,
        ];
    }

    private function saveStudentProfile(User $user, array $data, bool $complete = false): void
    {
        $attributes = array_intersect_key($data, array_flip(['education_level', 'study_field']));

        if (array_key_exists('university_id', $data)) {
            $university = $data['university_id'] === null
                ? null
                : University::query()->findOrFail($data['university_id']);

            $attributes['university_id'] = $university?->id;
            $attributes['university'] = $university?->name;
        } else {
            $university = $user->partnerUniversity;
        }

        if (array_key_exists('faculty_id', $data)) {
            $faculty = $data['faculty_id'] === null
                ? null
                : Faculty::query()->findOrFail($data['faculty_id']);

            $attributes['faculty_id'] = $faculty?->id;
            $attributes['faculty'] = $faculty?->name;
        } elseif (
            array_key_exists('university_id', $data)
            && $user->faculty_id !== null
            && $user->partnerFaculty?->university_id !== $university?->id
        ) {
            $attributes['faculty_id'] = null;
            $attributes['faculty'] = null;
        }

        if ($complete) {
            $attributes['onboarding_completed_at'] = now();
        }

        $user->fill($attributes)->save();
    }

    private function ensureCurrentPassword(User $user, string $password): void
    {
        if (! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Le mot de passe actuel est incorrect.'],
            ]);
        }
    }
}
