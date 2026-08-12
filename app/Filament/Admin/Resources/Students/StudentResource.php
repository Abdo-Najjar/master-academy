<?php

namespace App\Filament\Admin\Resources\Students;

use App\Filament\Admin\Resources\Students\Pages\EditStudent;
use App\Filament\Admin\Resources\Students\Pages\ListStudents;
use App\Filament\Admin\Resources\Students\Pages\ViewStudent;
use App\Filament\Admin\Resources\Students\RelationManagers\RegistrationsRelationManager;
use App\Filament\Admin\Resources\Students\RelationManagers\TransactionsRelationManager;
use App\Filament\Admin\Resources\Students\Schemas\StudentForm;
use App\Filament\Admin\Resources\Students\Schemas\StudentInfolist;
use App\Filament\Admin\Resources\Students\Tables\StudentsTable;
use App\Filament\Admin\Resources\Users\RelationManagers\LoginActivitiesRelationManager;
use App\Filament\Support\AuthorizesResourceActions;
use App\Models\Student;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class StudentResource extends Resource
{
    use AuthorizesResourceActions;

    protected static ?string $model = Student::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): ?string
    {
        return __('Students');
    }

    public static function getModelLabel(): string
    {
        return __('Student');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Students');
    }

    public static function permissionPrefix(): string
    {
        return 'student';
    }

    /**
     * Students are only ever created through Quick Enroll, which registers them
     * in a section and takes the payment in the same step. There is no plain
     * create page any more, so no create button should offer one.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return StudentForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return StudentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StudentsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RegistrationsRelationManager::class,
            TransactionsRelationManager::class,
            LoginActivitiesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStudents::route('/'),
            'view' => ViewStudent::route('/{record}'),
            'edit' => EditStudent::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['student_number', 'username', 'ssn', 'email', 'phone_number', 'whatsapp_number', 'name'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return array_filter([
            __('Student Number') => $record->student_number,
            __('National ID / SSN') => $record->ssn,
            __('Phone Number') => $record->phone_number,
            __('Email') => $record->email,
        ]);
    }
}
