<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProfessionalProfileResource\Pages\CreateProfessionalProfile;
use App\Filament\Resources\ProfessionalProfileResource\Pages\EditProfessionalProfile;
use App\Filament\Resources\ProfessionalProfileResource\Pages\ListProfessionalProfiles;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Modules\Appointments\Enums\ProfessionalSpecialty;
use Modules\Appointments\Models\ProfessionalProfile;
use Modules\Appointments\Notifications\ProfessionalInvitationNotification;
use Modules\Appointments\Services\ProfessionalProfileService;

class ProfessionalProfileResource extends Resource
{
    protected static ?string $model = ProfessionalProfile::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static string|\UnitEnum|null $navigationGroup = 'Mina Care';

    protected static ?string $modelLabel = 'Professionnel de santé';

    protected static ?string $pluralModelLabel = 'Professionnels de santé';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Compte professionnel')
                    ->schema([
                        Forms\Components\TextInput::make('account_name')
                            ->label('Nom du professionnel')
                            ->required()
                            ->maxLength(255)
                            ->visible(fn (string $operation): bool => $operation === 'create'),
                        Forms\Components\TextInput::make('email')
                            ->label('Adresse e-mail')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique('users', 'email')
                            ->visible(fn (string $operation): bool => $operation === 'create'),
                    ])
                    ->visible(fn (string $operation): bool => $operation === 'create'),
                Section::make('Profil public')
                    ->schema([
                        Forms\Components\TextInput::make('display_name')
                            ->label('Nom d’affichage')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('specialty')
                            ->label('Spécialité')
                            ->options(ProfessionalSpecialty::class)
                            ->default(ProfessionalSpecialty::Psychologue->value)
                            ->required(),
                        Forms\Components\TagsInput::make('languages')
                            ->label('Langues de consultation')
                            ->default(['Français'])
                            ->required(),
                        Forms\Components\Textarea::make('bio')
                            ->label('Présentation')
                            ->rows(4)
                            ->maxLength(2000),
                        Forms\Components\TextInput::make('photo_path')
                            ->label('Chemin de la photo')
                            ->maxLength(500),
                        Forms\Components\Toggle::make('is_verified')
                            ->label('Profil vérifié')
                            ->helperText('L’habilitation reste bloquée tant que la charte n’a pas été acceptée.')
                            ->default(false),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('display_name')
                    ->label('Nom d’affichage')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.email')
                    ->label('Adresse e-mail')
                    ->searchable(),
                Tables\Columns\TextColumn::make('specialty')
                    ->label('Spécialité')
                    ->badge(),
                Tables\Columns\IconColumn::make('is_verified')
                    ->label('Vérifié')
                    ->boolean(),
                Tables\Columns\IconColumn::make('charter_accepted_at')
                    ->label('Charte acceptée')
                    ->boolean(),
                Tables\Columns\IconColumn::make('user.is_active')
                    ->label('Compte actif')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Inscrit le')
                    ->dateTime('d/m/Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_verified')
                    ->label('Vérification'),
                Tables\Filters\SelectFilter::make('specialty')
                    ->label('Spécialité')
                    ->options(ProfessionalSpecialty::class),
            ])
            ->actions([
                Action::make('verify')
                    ->label('Vérifier et activer')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (ProfessionalProfile $record): bool => ! $record->is_verified)
                    ->action(function (ProfessionalProfile $record): void {
                        DB::transaction(function () use ($record): void {
                            $record->update(['is_verified' => true]);
                            $record->user?->update(['is_active' => true]);
                            app(ProfessionalProfileService::class)->syncLegacyProfile($record);
                        });

                        Notification::make()->title('Professionnel vérifié et activé.')->success()->send();
                    }),
                Action::make('suspend')
                    ->label('Suspendre')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (ProfessionalProfile $record): bool => $record->is_verified || $record->user?->is_active)
                    ->action(function (ProfessionalProfile $record): void {
                        DB::transaction(function () use ($record): void {
                            $record->update(['is_verified' => false]);
                            $record->user?->update(['is_active' => false]);
                            app(ProfessionalProfileService::class)->syncLegacyProfile($record);
                        });

                        Notification::make()->title('Accès professionnel suspendu.')->warning()->send();
                    }),
                Action::make('resend_invitation')
                    ->label('Renvoyer l’invitation')
                    ->icon('heroicon-o-envelope')
                    ->color('info')
                    ->action(function (ProfessionalProfile $record): void {
                        $user = $record->user;
                        abort_unless($user !== null, 404);

                        $token = Password::broker()->createToken($user);
                        $user->notify(new ProfessionalInvitationNotification($token));

                        Notification::make()->title('Invitation renvoyée.')->success()->send();
                    }),
                EditAction::make()->label('Modifier'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProfessionalProfiles::route('/'),
            'create' => CreateProfessionalProfile::route('/create'),
            'edit' => EditProfessionalProfile::route('/{record}/edit'),
        ];
    }
}
