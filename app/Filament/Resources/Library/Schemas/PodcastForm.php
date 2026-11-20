<?php

namespace App\Filament\Resources\Library\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PodcastForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('Titre')
                    ->required()
                    ->maxLength(255),
                Select::make('subject_id')
                    ->label('Matière')
                    ->relationship('subject', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('level_id')
                    ->label('Niveau')
                    ->relationship('level', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Textarea::make('description')
                    ->label('Description')
                    ->rows(2)
                    ->maxLength(1000),
                FileUpload::make('original_path')
                    ->label('Fichier audio')
                    ->disk(config('library.audio_disk'))
                    ->directory(config('library.originals_directory'))
                    ->acceptedFileTypes(config('library.accepted_audio_types'))
                    ->maxSize(config('library.max_audio_mb') * 1024)
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->helperText(fn (): string => 'Formats acceptés : MP3, M4A, WAV, OGG, AAC. Taille maximale : '.config('library.max_audio_mb').' Mo. La compression est lancée automatiquement.')
                    ->columnSpanFull(),
                Toggle::make('is_published')
                    ->label('Publié')
                    ->default(false),
            ])
            ->columns(2);
    }
}
