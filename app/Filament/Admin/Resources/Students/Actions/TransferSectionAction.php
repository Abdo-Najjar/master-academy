<?php

namespace App\Filament\Admin\Resources\Students\Actions;

use App\Models\Registration;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentSectionTransfer;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TransferSectionAction
{
    public static function make(): Action
    {
        return Action::make('transferSection')
            ->label(__('Transfer to Another Section'))
            ->icon('heroicon-o-arrows-right-left')
            ->color('warning')
            ->schema(function (Student $record): array {
                $currentSectionIds = $record->registrations()->pluck('section_id')->toArray();

                return [
                    Select::make('from_section_id')
                        ->label(__('From Section'))
                        ->options(
                            Section::whereIn('id', $currentSectionIds)
                                ->get()
                                ->pluck('name', 'id')
                        )
                        ->required()
                        ->searchable(),
                    Select::make('to_section_id')
                        ->label(__('To Section'))
                        ->options(
                            Section::whereNotIn('id', $currentSectionIds)
                                ->get()
                                ->pluck('name', 'id')
                        )
                        ->required()
                        ->searchable(),
                    TextInput::make('reason')
                        ->label(__('Reason'))
                        ->maxLength(255),
                ];
            })
            ->action(function (Student $record, array $data): void {
                DB::transaction(function () use ($record, $data): void {
                    $registration = Registration::where('student_id', $record->id)
                        ->where('section_id', $data['from_section_id'])
                        ->first();

                    if (! $registration) {
                        Notification::make()->danger()->title(__('Registration not found'))->send();

                        return;
                    }

                    $newSection = Section::find($data['to_section_id']);
                    if (! $newSection) {
                        return;
                    }

                    StudentSectionTransfer::create([
                        'student_id' => $record->id,
                        'from_section_id' => $data['from_section_id'],
                        'to_section_id' => $data['to_section_id'],
                        'reason' => $data['reason'] ?? null,
                        'transferred_by' => Auth::id(),
                        'transferred_at' => now(),
                    ]);

                    $registration->update([
                        'section_id' => $data['to_section_id'],
                        'amount_due' => $newSection->price,
                    ]);
                });

                Notification::make()
                    ->success()
                    ->title(__('Student transferred successfully'))
                    ->send();
            });
    }
}
