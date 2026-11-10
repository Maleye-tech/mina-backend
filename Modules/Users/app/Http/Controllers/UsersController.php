<?php

namespace Modules\Users\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Auth\Enums\UserRole;
use Modules\Users\Http\Requests\StoreUserRequest;
use Modules\Users\Http\Requests\UpdateUserRequest;

class UsersController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::query()->latest('id');

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($role = trim((string) $request->query('role'))) {
            $query->where('role', $role);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $users = $query
            ->paginate(min($request->integer('per_page', 15), 100))
            ->through(fn (User $user): array => $this->present($user));

        return response()->json($users);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'password' => Str::random(64),
            'role' => UserRole::from($request->validated('role')),
        ]);

        $resetStatus = Password::sendResetLink(['email' => $user->email]);

        if ($resetStatus !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages([
                'email' => ['Le lien de définition du mot de passe n’a pas pu être envoyé.'],
            ]);
        }

        $user->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Compte créé. Les liens de définition du mot de passe et de vérification ont été envoyés.',
            'user' => $this->present($user),
        ], 201);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json(['user' => $this->present($user)]);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $data = $request->validated();

        if ($user->is($request->user())) {
            if (array_key_exists('role', $data)) {
                throw ValidationException::withMessages([
                    'role' => ['Vous ne pouvez pas modifier votre propre rôle.'],
                ]);
            }

            if (array_key_exists('is_active', $data)) {
                throw ValidationException::withMessages([
                    'is_active' => ['Vous ne pouvez pas désactiver votre propre compte.'],
                ]);
            }
        }

        $removesAdminRights = $user->hasRole(UserRole::Admin) && (
            (array_key_exists('role', $data) && $data['role'] !== UserRole::Admin->value)
            || (array_key_exists('is_active', $data) && ! $data['is_active'])
        );

        if ($removesAdminRights && ! $this->hasAnotherActiveAdmin($user)) {
            $key = array_key_exists('role', $data) && $data['role'] !== UserRole::Admin->value
                ? 'role'
                : 'is_active';

            throw ValidationException::withMessages([
                $key => ['Le dernier administrateur actif ne peut pas être remplacé ni désactivé.'],
            ]);
        }

        if (array_key_exists('email', $data) && $data['email'] !== $user->email) {
            $user->email_verified_at = null;
        }

        $user->fill($data)->save();

        if (array_key_exists('is_active', $data) && ! $data['is_active']) {
            $user->tokens()->delete();
        }

        return response()->json([
            'message' => 'Le compte a été mis à jour.',
            'user' => $this->present($user->fresh()),
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        abort_if(
            $user->is($request->user()),
            403,
            'Vous ne pouvez pas supprimer votre propre compte.',
        );

        DB::transaction(function () use ($user): void {
            $user->tokens()->delete();
            $user->delete();
        });

        return response()->json(['message' => 'Le compte a été supprimé.']);
    }

    private function hasAnotherActiveAdmin(User $user): bool
    {
        return User::query()
            ->where('role', UserRole::Admin)
            ->whereKeyNot($user->getKey())
            ->where('is_active', true)
            ->exists();
    }

    private function present(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'role_label' => $user->role->label(),
            'is_active' => $user->is_active,
            'email_verified' => $user->hasVerifiedEmail(),
            'created_at' => $user->created_at,
        ];
    }
}
