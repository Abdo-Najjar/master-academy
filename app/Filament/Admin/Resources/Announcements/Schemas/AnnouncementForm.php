<?php

namespace App\Filament\Admin\Resources\Announcements\Schemas;

use App\Models\Section;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section as FormSection;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class AnnouncementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                FormSection::make('')
                    ->schema([
                        TextInput::make('title')
                            ->label(__('Title'))
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Textarea::make('body')
                            ->label(__('Body'))
                            ->required()
                            ->rows(5)
                            ->columnSpanFull(),

                        Toggle::make('all_sections')
                            ->label(__('Send to all sections'))
                            ->live()
                            ->inline(false)
                            ->columnSpanFull(),

                        Select::make('sections')
                            ->label(__('Target Sections'))
                            ->relationship('sections', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->visible(fn (callable $get) => ! $get('all_sections'))
                            ->columnSpanFull(),

                        DateTimePicker::make('published_at')
                            ->label(__('Publish at'))
                            ->native(false)
                            ->default(now())
                            ->helperText(__('Hidden until this date. Leave empty to publish immediately.')),

                        DateTimePicker::make('expires_at')
                            ->label(__('Expires at'))
                            ->native(false)
                            ->helperText(__('Hidden after this date. Leave empty for no expiry.')),

                        TextInput::make('created_by')
                            ->default(fn () => Auth::id())
                            ->visible(false)
                            ->dehydrated(fn ($state) => filled($state)),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }
}
