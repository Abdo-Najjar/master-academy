<?php

namespace App\Filament\Admin\Resources\Assignments\Schemas;

use App\Models\Section;
use App\Models\Trainer;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section as FormSection;
use Filament\Schemas\Schema;

class AssignmentForm
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
                        Select::make('trainer_id')
                            ->label(__('Trainer'))
                            ->options(fn () => Trainer::all()->mapWithKeys(fn (Trainer $t) => [
                                $t->id => $t->getTranslation('name', app()->getLocale(), false),
                            ]))
                            ->searchable()
                            ->nullable(),
                        TextInput::make('title')
                            ->label(__('Title'))
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        DateTimePicker::make('due_date')
                            ->label(__('Due Date'))
                            ->native(false),
                        TextInput::make('max_points')
                            ->label(__('Max Points'))
                            ->numeric()
                            ->minValue(0),
                        Textarea::make('description')
                            ->label(__('Description'))
                            ->rows(4)
                            ->columnSpanFull(),
                        SpatieMediaLibraryFileUpload::make('attachments')
                            ->label(__('Attachments'))
                            ->multiple()
                            ->collection('attachments')
                            ->maxSize(20 * 1024)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }
}
