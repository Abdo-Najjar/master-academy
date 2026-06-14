<?php

namespace App\Filament\Admin\Resources\CertificateTemplates\Schemas;

use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CertificateTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('')
                    ->schema([
                        TextInput::make('name')
                            ->label(__('Template Name'))
                            ->required()
                            ->maxLength(255),
                        Toggle::make('is_active')
                            ->label(__('Active'))
                            ->default(true)
                            ->inline(false),
                        SpatieMediaLibraryFileUpload::make('background')
                            ->label(__('Background Image'))
                            ->collection('background')
                            ->image()
                            ->imageEditor()
                            ->columnSpanFull()
                            ->helperText(__('Upload the certificate background image. Then use the Design button to position text fields.')),
                        TextInput::make('canvas_width')
                            ->label(__('Canvas Width (px)'))
                            ->numeric()
                            ->default(1000)
                            ->required(),
                        TextInput::make('canvas_height')
                            ->label(__('Canvas Height (px)'))
                            ->numeric()
                            ->default(700)
                            ->required(),
                    ])
                    ->columns(2),
            ]);
    }
}
