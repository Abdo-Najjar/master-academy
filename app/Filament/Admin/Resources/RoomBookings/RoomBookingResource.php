<?php

namespace App\Filament\Admin\Resources\RoomBookings;

use App\Filament\Admin\Resources\RoomBookings\Pages\CreateRoomBooking;
use App\Filament\Admin\Resources\RoomBookings\Pages\EditRoomBooking;
use App\Filament\Admin\Resources\RoomBookings\Pages\ListRoomBookings;
use App\Filament\Admin\Resources\RoomBookings\Pages\ViewRoomBooking;
use App\Filament\Admin\Resources\RoomBookings\RelationManagers\PaymentsRelationManager;
use App\Filament\Admin\Resources\RoomBookings\Schemas\RoomBookingForm;
use App\Filament\Admin\Resources\RoomBookings\Schemas\RoomBookingInfolist;
use App\Filament\Admin\Resources\RoomBookings\Tables\RoomBookingsTable;
use App\Filament\Support\AuthorizesResourceActions;
use App\Models\RoomBooking;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RoomBookingResource extends Resource
{
    use AuthorizesResourceActions;

    protected static ?string $model = RoomBooking::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getNavigationGroup(): ?string
    {
        return __('Education');
    }

    public static function getModelLabel(): string
    {
        return __('Room Booking');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Room Bookings');
    }

    public static function permissionPrefix(): string
    {
        return 'room_booking';
    }

    public static function form(Schema $schema): Schema
    {
        return RoomBookingForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return RoomBookingInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RoomBookingsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            PaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoomBookings::route('/'),
            'create' => CreateRoomBooking::route('/create'),
            'view' => ViewRoomBooking::route('/{record}'),
            'edit' => EditRoomBooking::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
