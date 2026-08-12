<?php

namespace App\Filament\Admin\Resources\Trainers;

use App\Filament\Admin\Resources\Trainers\Pages\CreateTrainer;
use App\Filament\Admin\Resources\Trainers\Pages\EditTrainer;
use App\Filament\Admin\Resources\Trainers\Pages\ListTrainers;
use App\Filament\Admin\Resources\Trainers\Pages\ViewTrainer;
use App\Filament\Admin\Resources\Trainers\RelationManagers\AssignmentsRelationManager;
use App\Filament\Admin\Resources\Trainers\RelationManagers\ExamsRelationManager;
use App\Filament\Admin\Resources\Trainers\RelationManagers\SectionsRelationManager;
use App\Filament\Admin\Resources\Trainers\RelationManagers\TransactionsRelationManager;
use App\Filament\Admin\Resources\Trainers\Schemas\TrainerForm;
use App\Filament\Admin\Resources\Trainers\Schemas\TrainerInfolist;
use App\Filament\Admin\Resources\Trainers\Tables\TrainersTable;
use App\Filament\Admin\Resources\Users\RelationManagers\LoginActivitiesRelationManager;
use App\Filament\Support\AuthorizesResourceActions;
use App\Models\Trainer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TrainerResource extends Resource
{
    use AuthorizesResourceActions;

    protected static ?string $model = Trainer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?int $navigationSort = 6;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): ?string
    {
        return __('Education');
    }

    public static function getModelLabel(): string
    {
        return __('Trainer');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Trainers');
    }

    public static function permissionPrefix(): string
    {
        return 'trainer';
    }

    public static function form(Schema $schema): Schema
    {
        return TrainerForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TrainerInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TrainersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            SectionsRelationManager::class,
            ExamsRelationManager::class,
            AssignmentsRelationManager::class,
            TransactionsRelationManager::class,
            LoginActivitiesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTrainers::route('/'),
            'create' => CreateTrainer::route('/create'),
            'view' => ViewTrainer::route('/{record}'),
            'edit' => EditTrainer::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['trainer_number', 'username', 'ssn', 'email', 'phone_number', 'whatsapp_number', 'name'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return array_filter([
            __('Trainer Number') => $record->trainer_number,
            __('National ID / SSN') => $record->ssn,
            __('Phone Number') => $record->phone_number,
            __('Email') => $record->email,
        ]);
    }
}
