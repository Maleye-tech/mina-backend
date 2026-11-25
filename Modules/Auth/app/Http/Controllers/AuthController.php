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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Appointments\Models\AppointmentProfessional;
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
use Modules\Auth\Notifications\AdminLoginCode;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    private const int MAX_LOGIN_CODE_ATTEMPTS = 5;

    private const int LOGIN_CODE_TTL_MINUTES = 10;

    #[OA\Post(
        path: '/auth/register',
        operationId: 'register',
        tags: ['Auth'],
        summary: 'Créer un compte étudiant',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'password', 'password_confirmation'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Camille Dupont'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'camille@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', minLength: 8),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Compte créé, e-mail de vérification envoyé',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(property: 'token', type: 'string', description: 'Jeton Sanctum (full-access)'),
                        new OA\Property(property: 'user', ref: '#/components/schemas/User'),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Données invalides', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'errors', type: 'object', additionalProperties: true),
                ]
            )),
            new OA\Response(response: 429, description: 'Trop de tentatives'),
        ]
    )]
    public function register(RegisterRequest $request): JsonResponse
    {
        $roleInput = $request->validated('role', 'student');
        $role = UserRole::tryFrom($roleInput) ?? UserRole::Student;

        // Sécurité : interdire la création d'un compte admin via l'inscription publique
        if ($role === UserRole::Admin) {
            $role = UserRole::Student;
        }

        $userData = [
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => $request->validated('password'),
            'role' => $role,
            'pseudonym' => $request->validated('pseudonym'),
        ];

        if ($request->filled('partner_institution_id')) {
            $userData['partner_institution_id'] = $request->validated('partner_institution_id');
        }
        if ($request->filled('partner_position')) {
            $userData['partner_position'] = $request->validated('partner_position');
        }
        if ($request->filled('university_id')) {
            $userData['university_id'] = $request->validated('university_id');
            $university = University::find($request->validated('university_id'));
            $userData['university'] = $university?->name;
        }
        if ($request->filled('faculty_id')) {
            $userData['faculty_id'] = $request->validated('faculty_id');
            $faculty = Faculty::find($request->validated('faculty_id'));
            $userData['faculty'] = $faculty?->name;
        }
        if ($request->filled('study_field')) {
            $userData['study_field'] = $request->validated('study_field');
        }
        if ($request->filled('education_level')) {
            $userData['education_level'] = $request->validated('education_level');
        }

        $user = User::create($userData);

        if ($role === UserRole::Professional) {
            AppointmentProfessional::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'title' => $request->input('professional_title', 'Psychologue / Conseiller'),
                    'is_available' => true,
                ]
            );
        }

        $user->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Compte créé. Vérifiez votre adresse e-mail pour la confirmer.',
            'user' => $this->present($user->fresh()),
            'token' => $user->createToken('mina-app', ['full-access'])->plainTextToken,
        ], 201);
    }

    #[OA\Post(
        path: '/auth/login',
        operationId: 'login',
        tags: ['Auth'],
        summary: 'Se connecter',
        description: 'Renvoie un jeton Sanctum. Un administrateur reçoit d\'abord un code 2FA par e-mail (requires_two_factor).',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email'),
                    new OA\Property(property: 'password', type: 'string', format: 'password'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Connexion réussie (ou challenge 2FA pour un admin)',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(property: 'token', type: 'string', nullable: true),
                        new OA\Property(property: 'user', ref: '#/components/schemas/User'),
                        new OA\Property(property: 'requires_two_factor', type: 'boolean'),
                        new OA\Property(property: 'challenge_token', type: 'string', nullable: true),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Identifiants incorrects ou compte désactivé', content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'errors', type: 'object', additionalProperties: true),
                ]
            )),
            new OA\Response(response: 429, description: 'Trop de tentatives'),
        ]
    )]
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
            $code = (string) random_int(100000, 999999);

            $user->forceFill([
                'login_code_hash' => Hash::make($code),
                'login_code_expires_at' => now()->addMinutes(self::LOGIN_CODE_TTL_MINUTES),
                'login_code_attempts' => 0,
            ])->save();

            $user->notify(new AdminLoginCode($code));

            Log::info("Code de connexion admin ({$user->email}) : {$code}");

            $token = $user->createToken(
                'admin-email-challenge',
                ['two-factor-challenge'],
                now()->addMinutes(self::LOGIN_CODE_TTL_MINUTES + 5),
            );

            return response()->json([
                'message' => 'Un code de connexion a été envoyé à votre adresse e-mail.',
                'requires_two_factor' => true,
                'requires_two_factor_setup' => false,
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

    public function completeTwoFactorChallenge(VerifyTwoFactorCodeRequest $request): JsonResponse
    {
        $userId = $request->user()->getKey();
        $challengeToken = $request->user()->currentAccessToken();

        $outcome = DB::transaction(function () use ($request, $userId, $challengeToken): array {
            $user = User::query()->lockForUpdate()->findOrFail($userId);

            $codeIsLive = $user->login_code_hash !== null
                && $user->login_code_expires_at !== null
                && $user->login_code_expires_at->isFuture();

            $valid = $codeIsLive
                && $user->login_code_attempts < self::MAX_LOGIN_CODE_ATTEMPTS
                && Hash::check($request->validated('code'), $user->login_code_hash);

            if (! $valid) {
                if ($codeIsLive) {
                    $attempts = $user->login_code_attempts + 1;

                    $user->forceFill([
                        'login_code_attempts' => $attempts,
                        ...($attempts >= self::MAX_LOGIN_CODE_ATTEMPTS
                            ? ['login_code_hash' => null, 'login_code_expires_at' => null]
                            : []),
                    ])->save();
                }

                Log::warning('Code de connexion admin invalide', [
                    'email' => $user->email,
                    'tentatives' => $user->login_code_attempts,
                    'code_expire' => ! $codeIsLive,
                ]);

                return ['valid' => false];
            }

            $user->forceFill([
                'login_code_hash' => null,
                'login_code_expires_at' => null,
                'login_code_attempts' => 0,
            ])->save();

            $challengeToken->delete();

            return [
                'valid' => true,
                'token' => $user->createToken('mina-app', ['full-access'])->plainTextToken,
            ];
        });

        if (! $outcome['valid']) {
            throw ValidationException::withMessages([
                'code' => ['Le code de vérification est invalide ou a expiré.'],
            ]);
        }

        return response()->json([
            'message' => 'Connexion réussie.',
            'token' => $outcome['token'],
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
        $roleProfile = match ($user->role) {
            UserRole::Student => [
                'type' => 'student',
                'education_level' => $user->education_level,
                'study_field' => $user->study_field,
                'university_id' => $user->university_id,
                'faculty' => $user->faculty,
                'faculty_id' => $user->faculty_id,
                'university' => $user->university,
                'onboarding_completed' => $user->onboarding_completed_at !== null,
            ],
            UserRole::Ambassador => [
                'type' => 'ambassador',
                'university_id' => $user->university_id,
                'university' => $user->university,
                'faculty' => $user->faculty,
                'faculty_id' => $user->faculty_id,
                'is_verified' => (bool) $user->is_ambassador_verified,
                'training_date' => $user->ambassador_training_date,
                'onboarding_completed' => $user->onboarding_completed_at !== null,
            ],
            UserRole::Professional => [
                'type' => 'professional',
                'title' => $user->professionalProfile?->title ?? 'Psychologue clinicien',
                'bio' => $user->professionalProfile?->bio,
                'phone' => $user->professionalProfile?->phone,
                'is_available' => (bool) ($user->professionalProfile?->is_available ?? true),
                'onboarding_completed' => true,
            ],
            UserRole::Partner => [
                'type' => 'partner',
                'institution_id' => $user->partner_institution_id,
                'institution_name' => $user->partnerInstitution?->name,
                'institution_type' => $user->partnerInstitution?->type,
                'position' => $user->partner_position,
                'onboarding_completed' => $user->partner_institution_id !== null,
            ],
            default => null,
        };

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
            'role_profile' => $roleProfile,
            'student_profile' => $user->hasRole(UserRole::Student, UserRole::Ambassador) ? [
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
