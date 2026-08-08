<?php

namespace App\Filament\Admin\Resources\Registrations\Actions;

use App\Models\Registration;
use App\Models\Section;
use App\Services\StudentTransferService;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Move a student to another group. The session counter and the paid-through
 * horizon travel with them — they do not start counting from zero — and the
 * move itself is recorded (from, to, reason, when, by whom).
 */
class TransferSectionAction
{
    public static function make(): Action
    {
        return Action::make('transferSection')
            ->label(__('Transfer to another section'))
            ->icon('heroicon-o-arrows-right-left')
            ->color('warning')
            ->visible(fn (?Registration $record): bool => $record !== null
                && (auth()->user()?->can('registration.transfer') ?? false))
            ->modalHeading(__('Transfer to another section'))
            ->modalSubmitActionLabel(__('Transfer'))
            ->schema(fn (Registration $record): array => [
                Placeholder::make('current')
                    ->label(__('Current Section'))
                    ->content($record->section?->name ?? '#'.$record->section_id),
                Placeholder::make('counter')
                    ->label(__('Sessions Counted'))
                    ->content(fn (): string => $record->isPerSessionBilled()
                        ? __(':counted of :paid paid sessions', [
                            'counted' => $record->sessions_counted,
                            'paid' => $record->paid_through_session,
                        ])
                        : __('Full course fee')),
                Select::make('to_section_id')
                    ->label(__('New Section'))
                    ->options(fn (): array => Section::query()
                        ->where('id', '!=', $record->section_id)
                        ->with('subject')
                        ->orderByDesc('id')
                        ->get()
                        ->mapWithKeys(fn (Section $s) => [
                            $s->id => $s->name.($s->subject
                                ? ' — '.$s->subject->getTranslation('name', app()->getLocale(), false)
                                : ''),
                        ])
                        ->all())
                    ->searchable()
                    ->required(),
                Textarea::make('reason')
                    ->label(__('Transfer Reason'))
                    ->rows(2)
                    ->maxLength(255)
                    ->required(),
            ])
            ->action(function (Registration $record, array $data): void {
                try {
                    StudentTransferService::transfer(
                        $record,
                        (int) $data['to_section_id'],
                        $data['reason'] ?? null,
                    );
                } catch (ValidationException $e) {
                    Notification::make()
                        ->danger()
                        ->title(__('Transfer failed'))
                        ->body(collect($e->errors())->flatten()->implode(' '))
                        ->send();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title(__('Student transferred successfully'))
                    ->send();
            });
    }
}
