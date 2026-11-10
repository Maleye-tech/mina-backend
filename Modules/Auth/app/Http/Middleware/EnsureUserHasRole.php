<?php

namespace Modules\Auth\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Modules\Auth\Enums\UserRole;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        $allowedRoles = array_filter(array_map(
            static fn (string $role): ?UserRole => UserRole::tryFrom($role),
            $roles,
        ));

        abort_unless(
            $user instanceof User && $allowedRoles !== [] && $user->hasRole(...$allowedRoles),
            403,
            'Vous n’avez pas le rôle requis pour accéder à cette ressource.',
        );

        return $next($request);
    }
}
