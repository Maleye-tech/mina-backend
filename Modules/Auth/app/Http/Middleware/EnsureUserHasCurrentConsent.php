<?php

namespace Modules\Auth\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasCurrentConsent
{
    public function handle(Request $request, Closure $next, string $consentType): Response
    {
        $user = $request->user();

        abort_unless(
            $user instanceof User && $user->hasCurrentConsent($consentType),
            403,
            'Le consentement requis est absent, retiré ou doit être renouvelé.',
        );

        return $next($request);
    }
}
