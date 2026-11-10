<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Modules\Auth\Enums\UserRole;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nom')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('Adresse e-mail')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('password')
                    ->label('Mot de passe')
                    ->password()
                    ->revealable()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->minLength(8),
                Select::make('role')
                    ->label('Rôle')
                    ->options(UserRole::class)
                    ->default(UserRole::Student)
                    ->required(),
                Toggle::make('is_active')
                    ->label('Compte actif')
                    ->default(true),
            ]);
    }
}
