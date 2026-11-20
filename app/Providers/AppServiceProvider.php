<?php

namespace App\Providers;

use App\Mail\BrevoTransport;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

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
