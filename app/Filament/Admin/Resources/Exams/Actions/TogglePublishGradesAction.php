<?php

namespace App\Filament\Admin\Resources\Exams\Actions;

use App\Models\Exam;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;

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

    public static function publishBulk(string $name = 'publishGradesBulk'): BulkAction
    {
        return BulkAction::make($name)
            ->label(__('Publish Grades'))
            ->icon('heroicon-o-eye')
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription(__('Students will be able to see their grades for the selected exams immediately.'))
            ->action(function (Collection $records): void {
                $records->each(fn (Exam $exam) => $exam->update(['grades_published_at' => now()]));

                Notification::make()
                    ->success()
                    ->title(__('Grades published for students'))
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    public static function unpublishBulk(string $name = 'unpublishGradesBulk'): BulkAction
    {
        return BulkAction::make($name)
            ->label(__('Unpublish Grades'))
            ->icon('heroicon-o-eye-slash')
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription(__('Students will no longer be able to see their grades for the selected exams.'))
            ->action(function (Collection $records): void {
                $records->each(fn (Exam $exam) => $exam->update(['grades_published_at' => null]));

                Notification::make()
                    ->success()
                    ->title(__('Grades hidden from students'))
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }
}
