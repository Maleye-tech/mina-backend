<?php

namespace App\Filament\Resources\Library\Schemas;

use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Modules\Library\Enums\ContentType;

class LibraryContentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('Titre')
                    ->required()
                    ->maxLength(255),
                Select::make('type')
                    ->label('Type')
                    ->options(ContentType::options())
                    ->default(ContentType::Course->value)
                    ->required(),
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
                    ->maxLength(255),
                RichEditor::make('body')
                    ->label('Contenu')
                    ->disableFileUploads()
                    ->columnSpanFull(),
                TextInput::make('sort_order')
                    ->label('Ordre')
                    ->numeric()
                    ->default(0)
                    ->required(),
                Toggle::make('is_published')
                    ->label('Publié')
                    ->default(false),
            ])
            ->columns(2);
    }
}
