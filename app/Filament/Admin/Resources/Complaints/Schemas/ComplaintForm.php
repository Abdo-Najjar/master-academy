<?php

namespace App\Filament\Admin\Resources\Complaints\Schemas;

use App\Models\Complaint;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ComplaintForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('')
                    ->schema([
                        TextInput::make('subject')
                            ->label(__('Subject'))
                            ->disabled()
                            ->columnSpanFull(),
                        Textarea::make('body')
                            ->label(__('Body'))
                            ->rows(4)
                            ->disabled()
                            ->columnSpanFull(),
                        Select::make('status')
                            ->label(__('Status'))
                            ->options(Complaint::statuses())
                            ->required()
                            ->native(false),
                        Textarea::make('admin_reply')
                            ->label(__('Admin Reply'))
                            ->rows(4)
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }
}
