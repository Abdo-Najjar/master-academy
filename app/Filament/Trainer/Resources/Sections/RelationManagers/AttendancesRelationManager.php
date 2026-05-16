<?php

namespace App\Filament\Trainer\Resources\Sections\RelationManagers;

use App\Models\Attendance;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AttendancesRelationManager extends RelationManager
{
    protected static string $relationship = 'attendances';

    protected static ?string $title = 'Attendance';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('student_id')
                    ->label(__('Student'))
                    ->options(fn () => $this->getOwnerRecord()->registrations()->with('student')->get()
                        ->mapWithKeys(fn ($r) => [$r->student_id => $r->student?->getTranslation('name', app()->getLocale(), false) ?? '#'.$r->student_id])
                        ->all())
                    ->required(),
                DatePicker::make('date')->native(false)->default(now())->required(),
                Select::make('status')
                    ->options([
                        'present' => __('Present'),
                        'absent' => __('Absent'),
                        'late' => __('Late'),
                        'excused' => __('Excused'),
                    ])
                    ->default('present')
                    ->required(),
                Textarea::make('note')->rows(2),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('student.name')->label(__('Student'))->searchable(),
                TextColumn::make('date')->label(__('Date'))->date()->sortable(),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'present' => 'success',
                        'absent' => 'danger',
                        'late' => 'warning',
                        'excused' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('note')->label(__('Note'))->limit(40),
            ])
            ->filters([
                Filter::make('date')
                    ->schema([
                        DatePicker::make('date')->native(false)->default(now()),
                    ])
                    ->query(fn (Builder $query, array $data) => filled($data['date'] ?? null)
                        ? $query->whereDate('date', $data['date'])
                        : $query),
            ])
            ->headerActions([
                CreateAction::make(),
                Action::make('markAllPresent')
                    ->label(__('Mark All Present Today'))
                    ->icon('heroicon-o-check-circle')
                    ->action(function () {
                        $sectionId = $this->getOwnerRecord()->id;
                        $studentIds = $this->getOwnerRecord()->registrations()->pluck('student_id');
                        foreach ($studentIds as $studentId) {
                            Attendance::query()->updateOrCreate(
                                ['section_id' => $sectionId, 'student_id' => $studentId, 'date' => now()->toDateString()],
                                ['status' => 'present']
                            );
                        }
                        Notification::make()->title(__('Attendance marked'))->success()->send();
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('date', 'desc');
    }
}
