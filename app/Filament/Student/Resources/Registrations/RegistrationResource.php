<?php

namespace App\Filament\Student\Resources\Registrations;

use App\Filament\Student\Resources\Registrations\Pages\ListRegistrations;
use App\Filament\Student\Resources\Registrations\Pages\ViewRegistration;
use App\Models\Registration;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;

class RegistrationResource extends Resource
{
    protected static ?string $model = Registration::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    public static function getModelLabel(): string
    {
        return __('Registration');
    }

    public static function getPluralModelLabel(): string
    {
        return __('My Sections');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('section.name')->label(__('Section'))->searchable(),
                TextColumn::make('section.subject.name')->label(__('Subject')),
                TextColumn::make('section.trainer.name')->label(__('Trainer')),
                TextColumn::make('section.start_date')->label(__('Start'))->date(),
                TextColumn::make('section.end_date')->label(__('End'))->date(),
                TextColumn::make('amount_paid')->label(__('Paid'))->money('USD'),
                TextColumn::make('created_at')->label(__('Enrolled'))->dateTime(),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        $studentId = Auth::guard('student')->id();

        return parent::getEloquentQuery()->where('student_id', $studentId);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRegistrations::route('/'),
            'view' => ViewRegistration::route('/{record}'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
