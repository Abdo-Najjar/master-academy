<?php

namespace App\Filament\Admin\Resources\CertificateTemplates\Schemas;

use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class CertificateTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('')
                    ->columnSpanFull()
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
                            ->live()
                            // Auto-fill canvas size from the uploaded image's real pixel
                            // dimensions, so the admin never has to enter them manually.
                            ->afterStateUpdated(function ($state, Set $set): void {
                                $file = is_array($state) ? reset($state) : $state;
                                if (! $file || ! method_exists($file, 'getRealPath')) {
                                    return;
                                }
                                $info = @getimagesize($file->getRealPath());
                                if ($info && $info[0] > 0 && $info[1] > 0) {
                                    $set('canvas_width', $info[0]);
                                    $set('canvas_height', $info[1]);
                                }
                            })
                            ->helperText(__('Upload the certificate background. Canvas size is detected automatically. Then use Design to position fields.')),
                        TextInput::make('canvas_width')
                            ->label(__('Canvas Width (px)'))
                            ->numeric()
                            ->default(1000)
                            ->required()
                            ->helperText(__('Auto-filled from the background image.')),
                        TextInput::make('canvas_height')
                            ->label(__('Canvas Height (px)'))
                            ->numeric()
                            ->default(700)
                            ->required()
                            ->helperText(__('Auto-filled from the background image.')),
                    ])
                    ->columns(2),
            ]);
    }
}
