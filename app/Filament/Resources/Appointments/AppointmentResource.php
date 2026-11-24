<?php

namespace App\Filament\Resources\Appointments;

use App\Filament\Resources\Appointments\Pages\ListAppointments;
use App\Filament\Resources\Appointments\Tables\AppointmentsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Modules\Appointments\Models\Appointment;
use UnitEnum;

class AppointmentResource extends Resource
{
    protected static ?string $model = Appointment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Mina Care (Santé mentale)';

    protected static ?string $navigationLabel = 'Rendez-vous';

    protected static ?string $modelLabel = 'rendez-vous';

    protected static ?string $pluralModelLabel = 'rendez-vous';

    public static function table(Table $table): Table
    {
        return AppointmentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAppointments::route('/'),
        ];
    }
}
