<?php

namespace App\Filament\Admin\Resources\CourseTypes\RelationManagers;

use App\Filament\Support\DeletionGuard;
use App\Filament\Support\TranslatableInput;
use App\Models\Subject;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ViewRecord;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Colors\Color;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class SubjectsRelationManager extends RelationManager
{
    protected static string $relationship = 'subjects';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Courses');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return is_subclass_of($pageClass, ViewRecord::class);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('')
                    ->schema([
                        TranslatableInput::make('name', __('Name')),
                        Select::make('trainers')
                            ->label(__('Trainers'))
                            ->multiple()
                            ->relationship('trainers', 'name')
                            ->searchable()
                            ->preload(),
                        ColorPicker::make('color')->label(__('Color')),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label(__('Name'))
                    ->badge()
                    ->color(fn ($record) => $record->color ? Color::hex($record->color) : 'gray')
                    ->searchable()
                    ->sortable(),
                ColorColumn::make('color')->label(__('Color')),
                TextColumn::make('trainers_count')->counts('trainers')->label(__('Trainers')),
                TextColumn::make('sections_count')->counts('sections')->label(__('Sections')),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make()
                    ->before(fn (Subject $record) => DeletionGuard::ensureUnused($record, [
                        'sections' => __('Sections'),
                    ])),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->emptyStateHeading(__('No records found'));
    }
}
