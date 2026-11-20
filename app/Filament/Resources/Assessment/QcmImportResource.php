<?php

namespace App\Filament\Resources\Assessment;

use App\Filament\Resources\Assessment\Pages\CreateQcmImport;
use App\Filament\Resources\Assessment\Pages\ListQcmImports;
use App\Filament\Resources\Assessment\Tables\QcmImportsTable;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Assessment\Enums\ImportStatus;
use Modules\Assessment\Models\QcmImport;
use UnitEnum;

class QcmImportResource extends Resource
{
    protected static ?string $model = QcmImport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static string|UnitEnum|null $navigationGroup = 'Évaluation';

    protected static ?string $navigationLabel = 'Imports QCM';

    protected static ?string $modelLabel = 'import de QCM';

    protected static ?string $pluralModelLabel = 'imports de QCM';

    protected static ?string $recordTitleAttribute = 'filename';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('file')
                    ->label('Fichier CSV ou Excel')
                    ->disk('local')
                    ->directory('assessment-imports')
                    ->rules(['mimes:csv,txt,xlsx', 'max:2048'])
                    ->maxSize(2048)
                    ->storeFileNamesIn('original_filename')
                    ->required()
                    ->helperText('Format .csv ou .xlsx (1re feuille). Colonnes attendues : voir la section « Import de QCM » du README. 2 Mo maximum.')
                    ->columnSpanFull(),
                Toggle::make('dry_run')
                    ->label('Test à blanc (dry run)')
                    ->helperText('Valide le fichier et compte les lignes sans enregistrer les questions.')
                    ->default(false),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return QcmImportsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListQcmImports::route('/'),
            'create' => CreateQcmImport::route('/create'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $pending = QcmImport::where('status', ImportStatus::Pending)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
