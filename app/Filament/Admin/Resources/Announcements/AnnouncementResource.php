<?php

namespace App\Filament\Admin\Resources\Announcements;

use App\Filament\Admin\Resources\Announcements\Pages\CreateAnnouncement;
use App\Filament\Admin\Resources\Announcements\Pages\EditAnnouncement;
use App\Filament\Admin\Resources\Announcements\Pages\ListAnnouncements;
use App\Filament\Admin\Resources\Announcements\Schemas\AnnouncementForm;
use App\Filament\Admin\Resources\Announcements\Tables\AnnouncementsTable;
use App\Models\Announcement;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Hexters\HexaLite\HasHexaLite;

class AnnouncementResource extends Resource
{
    use HasHexaLite;

    protected static ?string $model = Announcement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getNavigationGroup(): ?string
    {
        return __('Education');
    }

    public static function getModelLabel(): string
    {
        return __('Announcement');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Announcements');
    }

    public static function canAccess(): bool
    {
        return hexa()->can('announcement.index');
    }

    public function defineGates(): array
    {
        return [
            'announcement.index' => __('View'),
            'announcement.create' => __('Create'),
            'announcement.update' => __('Update'),
            'announcement.delete' => __('Delete'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return AnnouncementForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AnnouncementsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAnnouncements::route('/'),
            'create' => CreateAnnouncement::route('/create'),
            'edit' => EditAnnouncement::route('/{record}/edit'),
        ];
    }
}
