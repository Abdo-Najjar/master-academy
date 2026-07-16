<?php

namespace App\Filament\Admin\Resources\Exams\Schemas;

use App\Models\Section;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section as FormSection;
use Filament\Schemas\Schema;

class ExamForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                FormSection::make('')
                    ->schema([
                        Select::make('section_id')
                            ->label(__('Section'))
                            ->options(fn () => Section::query()->with('subject')->orderByDesc('id')->get()
                                ->mapWithKeys(fn ($s) => [
                                    $s->id => $s->getTranslation('name', app()->getLocale(), false)
                                        .($s->subject ? ' — '.$s->subject->getTranslation('name', app()->getLocale(), false) : ''),
                                ]))
                            ->searchable()
                            ->required(),
                        TextInput::make('name')
                            ->label(__('Exam Name'))
                            ->required()
                            ->maxLength(255),
                        DatePicker::make('date')
                            ->label(__('Date'))
                            ->native(false)
                            ->required(),
                        TextInput::make('max_score')
                            ->label(__('Max Score'))
                            ->numeric()
                            ->default(100)
                            ->minValue(1)
                            ->required(),
                        Textarea::make('note')
                            ->label(__('Note'))
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }
}
