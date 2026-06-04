<?php

namespace App\Filament\Admin\Resources\Complaints;

use App\Filament\Admin\Resources\Complaints\Pages\ListComplaints;
use App\Filament\Admin\Resources\Complaints\Pages\ViewComplaint;
use App\Filament\Admin\Resources\Complaints\Schemas\ComplaintForm;
use App\Filament\Admin\Resources\Complaints\Schemas\ComplaintInfolist;
use App\Filament\Admin\Resources\Complaints\Tables\ComplaintsTable;
use App\Models\Complaint;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Hexters\HexaLite\HasHexaLite;

class ComplaintResource extends Resource
{
    use HasHexaLite;

    protected static ?string $model = Complaint::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'subject';

    public static function getNavigationGroup(): ?string
    {
        return __('Communication');
    }

    public static function getModelLabel(): string
    {
        return __('Complaint');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Complaints');
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        // Hide archived (older than a month) complaints from the table, view and badge.
        return parent::getEloquentQuery()->notArchived();
    }

    public static function getNavigationBadge(): ?string
    {
        $open = Complaint::query()->notArchived()->where('status', Complaint::STATUS_OPEN)->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function canAccess(): bool
    {
        return hexa()->can('complaint.index');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public function defineGates(): array
    {
        return [
            'complaint.index' => __('View'),
            'complaint.update' => __('Update / Respond'),
            'complaint.delete' => __('Delete'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return ComplaintForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ComplaintInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ComplaintsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListComplaints::route('/'),
            'view' => ViewComplaint::route('/{record}'),
        ];
    }
}
