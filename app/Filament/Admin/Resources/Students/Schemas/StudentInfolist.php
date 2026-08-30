<?php

namespace App\Filament\Admin\Resources\Students\Schemas;

use App\Livewire\ScheduleCalendar;
use App\Models\Registration;
use App\Models\Student;
use App\Services\PaymentAllocationService;
use App\Support\CycleBreakdown;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StudentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('')
                    ->schema([
                        TextEntry::make('name')
                            ->label(__('Name'))
                            ->columnSpanFull(),
                        TextEntry::make('student_number')->label(__('Student Number'))->placeholder('—'),
                        TextEntry::make('username')->label(__('Username'))->placeholder('—'),
                        TextEntry::make('email')->label(__('Email'))->placeholder('—'),
                        TextEntry::make('ssn')->label(__('SSN'))->placeholder('—'),
                        TextEntry::make('dob')->label(__('Date of Birth'))->date()->placeholder('—'),
                        TextEntry::make('phone_number')->label(__('Phone'))->placeholder('—'),
                        TextEntry::make('whatsapp_number')->label(__('WhatsApp'))->placeholder('—'),
                        TextEntry::make('school')->label(__('School'))->placeholder('—'),
                        TextEntry::make('grade_level')->label(__('Grade Level'))->placeholder('—'),
                        TextEntry::make('enrolled_at')->label(__('Enrollment Date'))->date()->placeholder('—'),
                        TextEntry::make('governorate.name')->label(__('Governorate'))->placeholder('—'),
                        TextEntry::make('city.name')->label(__('City'))->placeholder('—'),
                        TextEntry::make('balanceFloat')
                            ->label(__('Wallet Balance'))
                            ->formatStateUsing(fn ($state) => number_format((float) $state, 2).' ₪')
                            ->color(fn ($state) => ((float) $state) < 0 ? 'danger' : 'success'),
                        TextEntry::make('created_at')->label(__('Created'))->dateTime()->placeholder('—'),
                        TextEntry::make('deleted_at')
                            ->label(__('Deleted'))
                            ->dateTime()
                            ->visible(fn (Student $record): bool => $record->trashed()),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                // The wallet is a single balance, which says how much the student
                // owes but never which course it is owed on. This breaks the same
                // money down the way the desk is asked about it — subject by
                // subject — and is what the deposit screen collects against.
                Section::make(__('Balance by course'))
                    ->description(__('What each course still owes, separately from the single wallet balance.'))
                    ->schema([
                        TextEntry::make('total_outstanding')
                            ->label(__('Total Outstanding'))
                            ->state(fn (Student $record): float => round($record->registrations
                                ->sum(fn (Registration $registration): float => PaymentAllocationService::amountDueNow($registration)), 2))
                            ->formatStateUsing(fn ($state): string => number_format((float) $state, 2).' ₪')
                            ->badge()
                            ->color(fn ($state): string => ((float) $state) > 0.009 ? 'danger' : 'success')
                            ->columnSpanFull(),

                        RepeatableEntry::make('registrations')
                            ->hiddenLabel()
                            ->schema([
                                TextEntry::make('section.subject.name')
                                    ->label(__('Course'))
                                    ->placeholder('—'),
                                TextEntry::make('section.name')
                                    ->label(__('Section'))
                                    ->placeholder('—'),
                                // Each amount carries how many months it is, so
                                // the row answers "how many months has he taken
                                // and how many is he behind" without anyone
                                // dividing by the monthly fee themselves.
                                TextEntry::make('amount_paid')
                                    ->label(__('Charged'))
                                    ->money('ILS')
                                    ->helperText(fn (Registration $record): ?string => CycleBreakdown::forAmount(
                                        $record->section,
                                        (float) $record->amount_paid,
                                    )),
                                TextEntry::make('funded_amount')
                                    ->label(__('Paid'))
                                    ->money('ILS')
                                    ->helperText(fn (Registration $record): ?string => CycleBreakdown::forAmount(
                                        $record->section,
                                        (float) $record->funded_amount,
                                    )),
                                TextEntry::make('outstanding')
                                    ->label(__('Remaining'))
                                    ->state(fn (Registration $record): float => PaymentAllocationService::amountDueNow($record))
                                    ->money('ILS')
                                    ->badge()
                                    ->color(fn ($state): string => ((float) $state) > 0.009 ? 'danger' : 'success')
                                    ->helperText(fn (Registration $record): ?string => CycleBreakdown::forOutstanding(
                                        $record,
                                        PaymentAllocationService::amountDueNow($record),
                                    )),
                                TextEntry::make('financial_status')
                                    ->label(__('Financial Status'))
                                    ->badge()
                                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                                        'ok' => __('Settled'),
                                        'warning' => __('Payment due soon'),
                                        'due' => __('Payment Due'),
                                        'overdue' => __('Overdue'),
                                        default => (string) $state,
                                    })
                                    ->color(fn (?string $state): string => match ($state) {
                                        'ok' => 'success',
                                        'warning', 'due' => 'warning',
                                        'overdue' => 'danger',
                                        default => 'gray',
                                    }),
                            ])
                            ->columns(6)
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (Student $record): bool => $record->registrations()->exists())
                    ->columnSpanFull(),

                Section::make(__('Schedule'))
                    ->description(__('Lessons of the sections this student is enrolled in.'))
                    ->schema([
                        Livewire::make(ScheduleCalendar::class, fn (Student $record): array => [
                            'sectionIds' => $record->registrations()
                                ->pluck('section_id')
                                ->filter()
                                ->unique()
                                ->values()
                                ->all(),
                        ])->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
