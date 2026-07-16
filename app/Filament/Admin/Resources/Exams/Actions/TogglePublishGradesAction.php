<?php

namespace App\Filament\Admin\Resources\Exams\Actions;

use App\Models\Exam;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/**
 * Toggles whether an exam's grades are visible to the student portal.
 * Shared by the Exam view/table and the Trainer page's Exams relation manager —
 * publishing from either surface immediately shows scores to students.
 */
class TogglePublishGradesAction
{
    public static function make(string $name = 'togglePublishGrades'): Action
    {
        return Action::make($name)
            ->label(fn (Exam $record): string => $record->isGradesPublished() ? __('Unpublish Grades') : __('Publish Grades'))
            ->icon(fn (Exam $record): string => $record->isGradesPublished() ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
            ->color(fn (Exam $record): string => $record->isGradesPublished() ? 'gray' : 'success')
            ->requiresConfirmation()
            ->modalDescription(fn (Exam $record): string => $record->isGradesPublished()
                ? __('Students will no longer be able to see their grades for this exam.')
                : __('Students will be able to see their grades for this exam immediately.'))
            ->action(function (Exam $record): void {
                $record->update([
                    'grades_published_at' => $record->isGradesPublished() ? null : now(),
                ]);

                Notification::make()
                    ->success()
                    ->title($record->isGradesPublished() ? __('Grades published for students') : __('Grades hidden from students'))
                    ->send();
            });
    }
}
