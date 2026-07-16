<?php

namespace App\Filament\Admin\Resources\Complaints\Schemas;

use App\Models\Complaint;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ComplaintInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Complaint'))
                    ->schema([
                        TextEntry::make('complainable_type')
                            ->label(__('From'))
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => class_basename($state) === 'Student' ? __('Student') : __('Trainer'))
                            ->color(fn (string $state): string => class_basename($state) === 'Student' ? 'info' : 'success'),
                        TextEntry::make('complainable.name')
                            ->label(__('Name'))
                            ->placeholder('—'),
                        TextEntry::make('status')
                            ->label(__('Status'))
                            ->badge()
                            ->formatStateUsing(fn (Complaint $record): string => $record->status_label)
                            ->color(fn (Complaint $record): string => $record->status_color),
                        TextEntry::make('created_at')
                            ->label(__('Submitted'))
                            ->dateTime()
                            ->placeholder('—'),
                        TextEntry::make('subject')
                            ->label(__('Subject'))
                            ->columnSpanFull(),
                        TextEntry::make('body')
                            ->label(__('Body'))
                            ->columnSpanFull(),
                    ])
                    ->columns(1),

                Section::make(__('Admin Reply'))
                    ->schema([
                        TextEntry::make('admin_reply')
                            ->hiddenLabel()
                            ->placeholder(__('No reply yet'))
                            ->columnSpanFull(),
                        TextEntry::make('handler.name')
                            ->label(__('Handled By'))
                            ->placeholder('—'),
                        TextEntry::make('resolved_at')
                            ->label(__('Resolved At'))
                            ->dateTime()
                            ->placeholder('—'),
                    ])
                    ->columns(1),
            ]);
    }
}
