<?php

namespace App\Providers;

use App\Mail\BrevoTransport;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Modules\Appointments\Models\Appointment;
use Modules\Appointments\Models\AvailabilitySlot;
use Modules\Appointments\Models\ProfessionalProfile;
use Modules\Appointments\Models\SessionNote;
use Modules\Appointments\Models\UrgentAlert;
use Modules\Appointments\Policies\AppointmentPolicy;
use Modules\Appointments\Policies\AvailabilitySlotPolicy;
use Modules\Appointments\Policies\ProfessionalProfilePolicy;
use Modules\Appointments\Policies\SessionNotePolicy;
use Modules\Appointments\Policies\UrgentAlertPolicy;
use Modules\Chat\Models\Conversation;
use Modules\Chat\Policies\ConversationPolicy as ChatConversationPolicy;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Mail::extend('brevo', fn (array $config): BrevoTransport => new BrevoTransport((string) $config['key']));

        Gate::policy(Appointment::class, AppointmentPolicy::class);
        Gate::policy(ProfessionalProfile::class, ProfessionalProfilePolicy::class);
        Gate::policy(AvailabilitySlot::class, AvailabilitySlotPolicy::class);
        Gate::policy(SessionNote::class, SessionNotePolicy::class);
        Gate::policy(UrgentAlert::class, UrgentAlertPolicy::class);
        Gate::policy(Conversation::class, ChatConversationPolicy::class);

        ResetPassword::createUrlUsing(function (User $user, string $token): string {
            return rtrim(config('app.frontend_url'), '/').'/reset-password?'.http_build_query([
                'token' => $token,
                'email' => $user->getEmailForPasswordReset(),
            ]);
        });

        VerifyEmail::createUrlUsing(function (User $user): string {
            return URL::temporarySignedRoute('api.verification.verify', now()->addMinutes(
                config('auth.verification.expire', 60),
            ), [
                'id' => $user->getKey(),
                'hash' => sha1($user->getEmailForVerification()),
            ]);
        });
    }
}
