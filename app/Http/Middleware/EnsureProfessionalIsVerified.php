<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Auth\Enums\UserRole;
use Symfony\Component\HttpFoundation\Response;

class EnsureProfessionalIsVerified
{
    public function handle(Request $request, Closure $next, ?string $mode = null): Response
    {
        $user = $request->user();

        if ($mode === 'optional' && ! $user?->hasRole(UserRole::Professional)) {
            return $next($request);
        }

        $profile = $user?->professionalCareProfile;

        abort_unless(
            $user?->hasRole(UserRole::Professional)
                && $user->is_active
                && $profile?->is_verified
                && $profile->hasAcceptedCharter(),
            403,
            'Votre profil doit être actif, vérifié et votre charte acceptée pour utiliser cet espace.',
        );

        return $next($request);
    }
}
