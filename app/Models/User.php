<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;
use Modules\Auth\Enums\UserRole;

class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, MustVerifyEmailContract
{
    use HasApiTokens, HasFactory, MustVerifyEmail, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'education_level',
        'study_field',
        'faculty',
        'university',
        'university_id',
        'faculty_id',
        'onboarding_completed_at',
        'pseudonym',
        'preferred_language',
        'notifications_enabled',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'app_authentication_recovery_codes',
    ];

    protected $attributes = [
        'role' => 'student',
        'is_active' => true,
        'preferred_language' => 'fr',
        'notifications_enabled' => true,
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'two_factor_secret' => 'encrypted',
            'two_factor_enabled_at' => 'datetime',
            'app_authentication_recovery_codes' => 'encrypted:array',
            'onboarding_completed_at' => 'datetime',
            'notifications_enabled' => 'boolean',
        ];
    }

    public function consents(): HasMany
    {
        return $this->hasMany(UserConsent::class);
    }

    public function hasCurrentConsent(string $consentType): bool
    {
        $latestConsent = $this->consents()
            ->where('consent_type', $consentType)
            ->latest('id')
            ->first();

        return $latestConsent?->granted === true
            && $latestConsent->version === config("privacy.consents.{$consentType}.version");
    }

    public function consentStatus(string $consentType): array
    {
        $latestConsent = $this->consents()
            ->where('consent_type', $consentType)
            ->latest('id')
            ->first();

        return [
            'granted' => $latestConsent?->granted === true
                && $latestConsent->version === config("privacy.consents.{$consentType}.version"),
            'version' => $latestConsent?->version,
            'recorded_at' => $latestConsent?->created_at,
            'current_version' => config("privacy.consents.{$consentType}.version"),
        ];
    }

    public function partnerUniversity(): BelongsTo
    {
        return $this->belongsTo(University::class, 'university_id');
    }

    public function partnerFaculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class, 'faculty_id');
    }

    public function getAppAuthenticationSecret(): ?string
    {
        return $this->two_factor_secret;
    }

    public function saveAppAuthenticationSecret(?string $secret): void
    {
        DB::transaction(function () use ($secret): void {
            $this->forceFill([
                'two_factor_secret' => $secret,
                'two_factor_enabled_at' => $secret ? now() : null,
                'two_factor_last_used_counter' => $secret ? intdiv(time(), 30) : null,
                'app_authentication_recovery_codes' => null,
            ])->save();

            if ($secret === null) {
                $this->tokens()->delete();
            }
        });
    }

    public function getAppAuthenticationHolderName(): string
    {
        return $this->email;
    }

    public function getAppAuthenticationRecoveryCodes(): ?array
    {
        return $this->app_authentication_recovery_codes;
    }

    public function saveAppAuthenticationRecoveryCodes(?array $codes): void
    {
        $this->forceFill(['app_authentication_recovery_codes' => $codes])->save();
    }

    public function hasRole(UserRole ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active && $this->role === UserRole::Admin;
    }
}
