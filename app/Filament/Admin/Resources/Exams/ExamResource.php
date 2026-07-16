<?php

namespace App\Filament\Admin\Resources\Exams;

use App\Filament\Admin\Resources\Exams\Pages\CreateExam;
use App\Filament\Admin\Resources\Exams\Pages\EditExam;
use App\Filament\Admin\Resources\Exams\Pages\ListExams;
use App\Filament\Admin\Resources\Exams\Pages\ViewExam;
use App\Filament\Admin\Resources\Exams\Schemas\ExamForm;
use App\Filament\Admin\Resources\Exams\Schemas\ExamInfolist;
use App\Filament\Admin\Resources\Exams\Tables\ExamsTable;
use App\Models\Exam;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Hexters\HexaLite\HasHexaLite;

class ExamResource extends Resource
{
    use HasHexaLite;

    protected static ?string $model = Exam::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): ?string
    {
        return __('Operations');
    }

    public static function getModelLabel(): string
    {
        return __('Exam');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Exams');
    }

    public static function canAccess(): bool
    {
        return hexa()->can('exam.index');
    }

    public function defineGates(): array
    {
        return [
            'exam.index' => __('View'),
            'exam.create' => __('Create'),
            'exam.update' => __('Update'),
            'exam.delete' => __('Delete'),
        ];
    }

    public static function form(Schema $schema): Schema
    {
        return ExamForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ExamInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ExamsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Admin\Resources\Exams\RelationManagers\GradesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExams::route('/'),
            'create' => CreateExam::route('/create'),
            'view' => ViewExam::route('/{record}'),
            'edit' => EditExam::route('/{record}/edit'),
        ];
    }
}
