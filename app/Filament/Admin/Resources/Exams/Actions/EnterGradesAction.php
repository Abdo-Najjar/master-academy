<?php

namespace App\Filament\Admin\Resources\Exams\Actions;

use App\Models\Exam;
use App\Models\ExamGrade;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

/**
 * Reusable action that lets a teacher enter/update every student's score for an
 * exam in a single modal. Shared by the Exam view page and the Trainer page.
 */
class EnterGradesAction
{
    public static function make(string $name = 'bulkGrade'): Action
    {
        return Action::make($name)
            ->label(__('Enter Grades'))
            ->icon('heroicon-o-pencil-square')
            ->color('primary')
            ->modalHeading(fn (?Exam $record): string => trim(__('Enter Grades').' — '.($record ? self::examName($record) : '')))
            ->schema(function (?Exam $record): array {
                if (! $record) {
                    return [];
                }

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
                    $name = is_array($student->name)
                        ? ($student->name[app()->getLocale()] ?? reset($student->name))
                        : $student->name;
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
            });
    }

    private static function examName(Exam $exam): string
    {
        return (string) ($exam->name ?? '');
    }
}
