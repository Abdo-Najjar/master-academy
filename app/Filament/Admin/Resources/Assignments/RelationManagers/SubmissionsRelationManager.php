<?php

namespace App\Filament\Admin\Resources\Assignments\RelationManagers;

use App\Models\AssignmentSubmission;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SubmissionsRelationManager extends RelationManager
{
    protected static string $relationship = 'submissions';

    public static function getTitle(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): string
    {
        return __('Submissions');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('student.name')->label(__('Student'))->searchable(),
                TextColumn::make('student.student_number')->label(__('Student Number')),
                TextColumn::make('submitted_at')->label(__('Submitted At'))->dateTime()->sortable()->placeholder('—'),
                TextColumn::make('grade')->label(__('Grade'))->placeholder('—')->badge()->color('info'),
                TextColumn::make('content')->label(__('Content'))->limit(40)->placeholder('—'),
            ])
            ->recordActions([
                Action::make('viewAttachment')
                    ->label(__('File'))
                    ->icon('heroicon-o-paper-clip')
                    ->color('gray')
                    ->visible(fn (AssignmentSubmission $record): bool => (bool) $record->getFirstMedia('attachment'))
                    ->url(fn (AssignmentSubmission $record): ?string => $record->getFirstMedia('attachment')?->getUrl())
                    ->openUrlInNewTab(),
                Action::make('grade')
                    ->label(__('Grade'))
                    ->icon('heroicon-o-pencil-square')
                    ->color('primary')
                    ->schema([
                        TextInput::make('grade')
                            ->label(__('Grade'))
                            ->numeric()
                            ->minValue(0),
                        Textarea::make('feedback')
                            ->label(__('Feedback'))
                            ->rows(3),
                    ])
                    ->fillForm(fn (AssignmentSubmission $record): array => [
                        'grade' => $record->grade,
                        'feedback' => $record->feedback,
                    ])
                    ->action(function (AssignmentSubmission $record, array $data): void {
                        $record->update([
                            'grade' => $data['grade'] !== '' ? $data['grade'] : null,
                            'feedback' => $data['feedback'],
                        ]);

                        Notification::make()->success()->title(__('Grade saved'))->send();
                    }),
            ])
            ->defaultSort('submitted_at', 'desc')
            ->emptyStateHeading(__('No records found'));
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
