<?php

namespace App\Filament\Resources\ProfessionalProfileResource\Pages;

use App\Filament\Resources\ProfessionalProfileResource;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Modules\Appointments\Models\ProfessionalProfile;
use Modules\Appointments\Notifications\ProfessionalInvitationNotification;
use Modules\Appointments\Services\ProfessionalProfileService;
use Modules\Auth\Enums\UserRole;

class CreateProfessionalProfile extends CreateRecord
{
    protected static string $resource = ProfessionalProfileResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data): ProfessionalProfile {
            $user = User::query()->create([
                'name' => $data['account_name'],
                'email' => $data['email'],
                'password' => Str::random(64),
                'role' => UserRole::Professional,
                'is_active' => true,
            ]);

            $profile = ProfessionalProfile::query()->create([
                'user_id' => $user->id,
                'display_name' => $data['display_name'],
                'specialty' => $data['specialty'],
                'languages' => $data['languages'],
                'bio' => $data['bio'] ?? null,
                'photo_path' => $data['photo_path'] ?? null,
                'is_verified' => $data['is_verified'] ?? false,
            ]);

            app(ProfessionalProfileService::class)->syncLegacyProfile($profile);

            $token = Password::broker()->createToken($user);
            $user->notify(new ProfessionalInvitationNotification($token));
            $user->sendEmailVerificationNotification();

            return $profile;
        });
    }

    protected function getRedirectUrl(): string
    {
        return static::$resource::getUrl('edit', ['record' => $this->getRecord()]);
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->title('Professionnel créé et invitation envoyée.')
            ->success();
    }
}
