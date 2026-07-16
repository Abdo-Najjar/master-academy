<?php

namespace App\Filament\Admin\Resources\Trainers\RelationManagers;

use App\Filament\Admin\Resources\Assignments\AssignmentResource;
use App\Models\Assignment;
use App\Models\Section;
use App\Models\Trainer;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AssignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'assignments';

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('Assignments');
    }

    public static function canViewForRecord(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): bool
    {
        return is_subclass_of($pageClass, \Filament\Resources\Pages\ViewRecord::class);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components($this->assignmentFields());
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('title')->label(__('Title'))->searchable()->sortable(),
                TextColumn::make('section.name')->label(__('Section'))->searchable(),
                TextColumn::make('due_date')->label(__('Due Date'))->dateTime()->sortable(),
                TextColumn::make('max_points')->label(__('Max Points')),
                TextColumn::make('submissions_count')->counts('submissions')->label(__('Submissions')),
            ])
            ->headerActions([
                Action::make('createAssignment')
                    ->label(__('New Assignment'))
                    ->icon('heroicon-o-plus')
                    ->color('success')
                    ->schema($this->assignmentFields())
                    ->action(function (array $data): void {
                        $owner = $this->getOwnerRecord();
                        $data['trainer_id'] = $owner instanceof Trainer ? $owner->id : null;
                        Assignment::create($data);
                        Notification::make()->success()->title(__('Saved successfully'))->send();
                    }),
            ])
            ->recordActions([
                Action::make('viewSubmissions')
                    ->label(__('Submissions'))
                    ->icon('heroicon-o-inbox-arrow-down')
                    ->color('primary')
                    ->url(fn (Assignment $record): string => AssignmentResource::getUrl('view', ['record' => $record])),
                ActionGroup::make([
                    EditAction::make()->schema($this->assignmentFields()),
                    DeleteAction::make(),
                ]),
            ])
            ->defaultSort('due_date', 'desc')
            ->emptyStateHeading(__('No records found'));
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    /** Assignment form fields with the section limited to this trainer's own sections. */
    protected function assignmentFields(): array
    {
        return [
            Select::make('section_id')
                ->label(__('Section'))
                ->options(fn (): array => $this->trainerSectionOptions())
                ->searchable()
                ->required(),
            TextInput::make('title')
                ->label(__('Title'))
                ->required()
                ->maxLength(255),
            DateTimePicker::make('due_date')
                ->label(__('Due Date'))
                ->native(false),
            TextInput::make('max_points')
                ->label(__('Max Points'))
                ->numeric()
                ->minValue(0),
            Textarea::make('description')
                ->label(__('Description'))
                ->rows(3)
                ->columnSpanFull(),
        ];
    }

    /** @return array<int, string> */
    protected function trainerSectionOptions(): array
    {
        $owner = $this->getOwnerRecord();

        if (! $owner instanceof Trainer) {
            return [];
        }

        return $owner->sections()
            ->with('subject')
            ->orderByDesc('id')
            ->get()
            ->mapWithKeys(fn (Section $s): array => [
                $s->id => $s->getTranslation('name', app()->getLocale(), false)
                    .($s->subject ? ' — '.$s->subject->getTranslation('name', app()->getLocale(), false) : ''),
            ])
            ->toArray();
    }
}
