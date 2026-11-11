<?php

namespace Modules\WellBeing\Filament\Resources\WellbeingArticleResource\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WellbeingArticleInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Fiche de prévention')
                    ->schema([
                        TextEntry::make('title')
                            ->label('Titre')
                            ->columnSpanFull(),
                        TextEntry::make('slug')
                            ->label('Slug'),
                        TextEntry::make('category')
                            ->label('Thématique')
                            ->badge(),
                        IconEntry::make('is_published')
                            ->label('Publiée')
                            ->boolean(),
                        TextEntry::make('content')
                            ->label('Contenu')
                            ->markdown()
                            ->columnSpanFull(),
                        TextEntry::make('created_at')
                            ->label('Créée le')
                            ->dateTime('d/m/Y H:i'),
                        TextEntry::make('updated_at')
                            ->label('Modifiée le')
                            ->dateTime('d/m/Y H:i'),
                    ])
                    ->columns(2),
            ]);
    }
}
