<?php

namespace App\Filament\Admin\Resources\Exams\Pages;

use App\Filament\Admin\Resources\Exams\ExamResource;
use App\Models\Exam;
use App\Models\ExamGrade;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewExam extends ViewRecord
{
    protected static string $resource = ExamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('bulkGrade')
                ->label(__('Enter Grades'))
                ->icon('heroicon-o-pencil-square')
                ->color('primary')
                ->schema(function (Exam $record) {
                    $record->loadMissing('section.registrations.student');
                    $existing = ExamGrade::query()
                        ->where('exam_id', $record->id)
                        ->pluck('score', 'student_id');

                    $fields = [];
                    foreach ($record->section?->registrations ?? [] as $reg) {
                        $student = $reg->student;
                        if (! $student) {
                            continue;
                        }
                        $name = is_array($student->name) ? ($student->name[app()->getLocale()] ?? reset($student->name)) : $student->name;
                        $fields[] = TextInput::make("scores.{$student->id}")
                            ->label($name.' ('.$student->student_number.')')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue((float) $record->max_score)
                            ->default($existing[$student->id] ?? null);
                    }
                    return $fields;
                })
                ->action(function (Exam $record, array $data): void {
                    foreach (($data['scores'] ?? []) as $studentId => $score) {
                        if ($score === null || $score === '') {
                            ExamGrade::query()
                                ->where('exam_id', $record->id)
                                ->where('student_id', (int) $studentId)
                                ->delete();
                            continue;
                        }
                        ExamGrade::query()->updateOrCreate(
                            ['exam_id' => $record->id, 'student_id' => (int) $studentId],
                            ['score' => (float) $score]
                        );
                    }
                    Notification::make()->success()->title(__('Grades saved'))->send();
                }),
            EditAction::make(),
        ];
    }
}
