<?php

namespace App\Filament\Resources\Assessment;

use App\Filament\Resources\Assessment\Pages\CreateClinicalCase;
use App\Filament\Resources\Assessment\Pages\EditClinicalCase;
use App\Filament\Resources\Assessment\Pages\ListClinicalCases;
use App\Filament\Resources\Assessment\Schemas\ClinicalCaseForm;
use App\Filament\Resources\Assessment\Tables\ClinicalCasesTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Assessment\Models\ClinicalCase;
use UnitEnum;

class ClinicalCaseResource extends Resource
{
    protected static ?string $model = ClinicalCase::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Évaluation';

    protected static ?string $navigationLabel = 'Cas cliniques';

    protected static ?string $modelLabel = 'cas clinique';

    protected static ?string $pluralModelLabel = 'cas cliniques';

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return ClinicalCaseForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ClinicalCasesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListClinicalCases::route('/'),
            'create' => CreateClinicalCase::route('/create'),
            'edit' => EditClinicalCase::route('/{record}/edit'),
        ];
    }
}
