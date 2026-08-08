<?php

namespace App\Filament\Admin\Resources\Exams\Actions;

use App\Models\Exam;
use App\Models\ExamGrade;
use App\Support\AuditReason;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;

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
                $existingScores = ExamGrade::query()
                    ->where('exam_id', $record->id)
                    ->pluck('score', 'student_id');
                $existingNotes = ExamGrade::query()
                    ->where('exam_id', $record->id)
                    ->pluck('note', 'student_id');

                $fields = [];
                foreach ($record->section?->registrations ?? [] as $reg) {
                    $student = $reg->student;
                    if (! $student) {
                        continue;
                    }
                    $name = is_array($student->name)
                        ? ($student->name[app()->getLocale()] ?? reset($student->name))
                        : $student->name;
                    $fields[] = Grid::make(2)
                        ->schema([
                            TextInput::make("scores.{$student->id}")
                                ->label($name.' ('.$student->student_number.')')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue((float) $record->max_score)
                                ->helperText(__('Out of :max', ['max' => $record->max_score]))
                                ->default($existingScores[$student->id] ?? null),
                            TextInput::make("notes.{$student->id}")
                                ->label(__('Note'))
                                ->maxLength(255)
                                ->default($existingNotes[$student->id] ?? null),
                        ]);
                }

                // Grade changes are audited, so the operator can say why.
                $fields[] = Textarea::make('audit_reason')
                    ->label(__('Reason for change'))
                    ->helperText(__('Recorded in the audit log with this change.'))
                    ->rows(2)
                    ->maxLength(500)
                    ->columnSpanFull();

                return $fields;
            })
            ->action(function (Exam $record, array $data): void {
                $notes = $data['notes'] ?? [];

                AuditReason::using($data['audit_reason'] ?? null, function () use ($record, $data, $notes): void {
                    foreach (($data['scores'] ?? []) as $studentId => $score) {
                        $note = $notes[$studentId] ?? null;
                        $note = $note === '' ? null : $note;

                        if ($score === null || $score === '') {
                            ExamGrade::query()
                                ->where('exam_id', $record->id)
                                ->where('student_id', (int) $studentId)
                                ->delete();

                            continue;
                        }

                        ExamGrade::query()->updateOrCreate(
                            ['exam_id' => $record->id, 'student_id' => (int) $studentId],
                            ['score' => (float) $score, 'note' => $note]
                        );
                    }
                });

                Notification::make()->success()->title(__('Grades saved'))->send();
            });
    }

    private static function examName(Exam $exam): string
    {
        return (string) ($exam->name ?? '');
    }
}
