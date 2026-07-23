<?php

namespace App\Filament\Admin\Resources\Trainers\RelationManagers;

use App\Filament\Admin\Resources\Exams\Actions\EnterGradesAction;
use App\Filament\Admin\Resources\Exams\Actions\TogglePublishGradesAction;
use App\Filament\Admin\Resources\Exams\ExamResource;
use App\Models\Exam;
use App\Models\Section;
use App\Models\Trainer;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ExamsRelationManager extends RelationManager
{
    protected static string $relationship = 'exams';

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('Exams & Grades');
    }

    public static function canViewForRecord(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): bool
    {
        return is_subclass_of($pageClass, \Filament\Resources\Pages\ViewRecord::class);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components($this->examFields());
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')->label(__('Exam Name'))->searchable()->sortable(),
                TextColumn::make('section.name')->label(__('Section'))->searchable(),
                TextColumn::make('section.subject.name')->label(__('Course'))->toggleable(),
                TextColumn::make('date')->label(__('Date'))->date()->sortable(),
                TextColumn::make('max_score')->label(__('Max Score')),
                TextColumn::make('grades_count')->counts('grades')->label(__('Graded')),
                IconColumn::make('grades_published_at')
                    ->label(__('Published'))
                    ->boolean()
                    ->state(fn (Exam $record): bool => $record->isGradesPublished()),
            ])
            ->headerActions([
                Action::make('createExam')
                    ->label(__('New Exam'))
                    ->icon('heroicon-o-plus')
                    ->color('success')
                    ->schema($this->examFields())
                    ->action(function (array $data): void {
                        Exam::create($data);
                        Notification::make()->success()->title(__('Saved successfully'))->send();
                    }),
            ])
            ->recordActions([
                EnterGradesAction::make(),
                TogglePublishGradesAction::make(),
                ActionGroup::make([
                    Action::make('viewGrades')
                        ->label(__('Grades'))
                        ->icon('heroicon-o-list-bullet')
                        ->color('gray')
                        ->url(fn (Exam $record): string => ExamResource::getUrl('view', ['record' => $record])),
                    EditAction::make()->schema($this->examFields()),
                    DeleteAction::make(),
                ]),
            ])
            ->defaultSort('date', 'desc')
            ->emptyStateHeading(__('No records found'));
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    /** Exam form fields with the section limited to this trainer's own sections. */
    protected function examFields(): array
    {
        return [
            Select::make('section_id')
                ->label(__('Section'))
                ->options(fn (): array => $this->trainerSectionOptions())
                ->searchable()
                ->required(),
            TextInput::make('name')
                ->label(__('Exam Name'))
                ->required()
                ->maxLength(255),
            DatePicker::make('date')
                ->label(__('Date'))
                ->native(false)
                ->required(),
            TextInput::make('max_score')
                ->label(__('Max Score'))
                ->numeric()
                ->default(100)
                ->minValue(1)
                ->required(),
            Textarea::make('note')
                ->label(__('Note'))
                ->rows(2)
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
