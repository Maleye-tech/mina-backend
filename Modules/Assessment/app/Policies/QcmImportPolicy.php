<?php

namespace Modules\Assessment\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;
use Modules\Assessment\Models\QcmImport;
use Modules\Auth\Enums\UserRole;

class QcmImportPolicy
{
    public function viewAny(User $user): Response
    {
        return $this->adminOnly($user, 'Seuls les administrateurs peuvent consulter les imports de QCM.');
    }

    public function view(User $user, QcmImport $import): Response
    {
        return $this->adminOnly($user, 'Seuls les administrateurs peuvent consulter cet import de QCM.');
    }

    public function create(User $user): Response
    {
        return $this->adminOnly($user, 'Seuls les administrateurs peuvent lancer des imports de QCM.');
    }

    public function delete(User $user, QcmImport $import): Response
    {
        return $this->adminOnly($user, 'Seuls les administrateurs peuvent supprimer des imports de QCM.');
    }

    private function adminOnly(User $user, string $message): Response
    {
        return $user->hasRole(UserRole::Admin)
            ? Response::allow()
            : Response::deny($message);
    }
}
