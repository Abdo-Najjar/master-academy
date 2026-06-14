<?php

namespace App\Filament\Admin\Resources\Parents\RelationManagers;

use App\Models\Student;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class StudentsRelationManager extends RelationManager
{
    protected static string $relationship = 'students';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Linked Students');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return is_subclass_of($pageClass, \Filament\Resources\Pages\ViewRecord::class);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('student_number')->label(__('Student Number'))->searchable(),
                TextColumn::make('name')->label(__('Name'))->searchable(),
                TextColumn::make('status')->label(__('Status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'suspended' => 'warning',
                        'withdrawn' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => __($state)),
                TextColumn::make('grade_level')->label(__('Grade'))->placeholder('—'),
                TextColumn::make('school')->label(__('School'))->placeholder('—'),
            ])
            ->headerActions([
                Action::make('link_student')
                    ->label(__('Link Student'))
                    ->schema([
                        Select::make('student_id')
                            ->label(__('Student'))
                            ->options(
                                Student::query()
                                    ->whereNull('parent_id')
                                    ->orWhere('parent_id', $this->ownerRecord->id)
                                    ->get()
                                    ->pluck('name', 'id')
                            )
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        Student::where('id', $data['student_id'])
                            ->update(['parent_id' => $this->ownerRecord->id]);
                    }),
            ])
            ->recordActions([
                Action::make('unlink')
                    ->label(__('Unlink'))
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (Student $record) => $record->update(['parent_id' => null])),
            ])
            ->toolbarActions([
                BulkActionGroup::make([]),
            ]);
    }
}
