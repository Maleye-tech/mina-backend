<?php

namespace Modules\WellBeing\Filament\Resources;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\WellBeing\Filament\Resources\EmergencyContactResource\Pages\CreateEmergencyContact;
use Modules\WellBeing\Filament\Resources\EmergencyContactResource\Pages\EditEmergencyContact;
use Modules\WellBeing\Filament\Resources\EmergencyContactResource\Pages\ListEmergencyContacts;
use Modules\WellBeing\Filament\Resources\EmergencyContactResource\Pages\ViewEmergencyContact;
use Modules\WellBeing\Filament\Resources\EmergencyContactResource\Schemas\EmergencyContactForm;
use Modules\WellBeing\Filament\Resources\EmergencyContactResource\Schemas\EmergencyContactInfolist;
use Modules\WellBeing\Filament\Resources\EmergencyContactResource\Tables\EmergencyContactsTable;
use Modules\WellBeing\Models\EmergencyContact;
use UnitEnum;

/**
 * Administration des contacts d'urgence Mina Care.
 */
class EmergencyContactResource extends Resource
{
    protected static ?string $model = EmergencyContact::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhone;

    protected static string|UnitEnum|null $navigationGroup = 'Bien-être';

    protected static ?string $navigationLabel = 'Contacts d’urgence';

    protected static ?string $modelLabel = 'contact d’urgence';

    protected static ?string $pluralModelLabel = 'contacts d’urgence';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return EmergencyContactForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return EmergencyContactInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EmergencyContactsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEmergencyContacts::route('/'),
            'create' => CreateEmergencyContact::route('/create'),
            'view' => ViewEmergencyContact::route('/{record}'),
            'edit' => EditEmergencyContact::route('/{record}/edit'),
        ];
    }
}
