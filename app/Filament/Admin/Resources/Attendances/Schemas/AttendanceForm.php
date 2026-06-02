<?php

namespace App\Filament\Admin\Resources\Attendances\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AttendanceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('')
                    ->schema([
                        Select::make('section_id')
                            ->label(__('Section'))
                            ->relationship('section', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('student_id')
                            ->label(__('Student'))
                            ->relationship('student', 'name')
                            ->searchable(['student_number', 'username'])
                            ->preload()
                            ->required(),
                        DatePicker::make('date')
                            ->label(__('Date'))
                            ->native(false)
                            ->default(now())
                            ->required(),
                        Select::make('status')
                            ->label(__('Status'))
                            ->options([
                                'present' => __('Present'),
                                'absent' => __('Absent'),
                                'late' => __('Late'),
                                'excused' => __('Excused'),
                            ])
                            ->default('present')
                            ->required(),
                        Textarea::make('note')
                            ->label(__('Note'))
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }
}
