<?php

namespace App\Filament\Admin\Resources\Sections\RelationManagers;

use App\Models\Room;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TimesRelationManager extends RelationManager
{
    protected static string $relationship = 'times';

    protected static ?string $title = 'Times';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('day')
                    ->label(__('Day'))
                    ->options([
                        'saturday' => __('Saturday'),
                        'sunday' => __('Sunday'),
                        'monday' => __('Monday'),
                        'tuesday' => __('Tuesday'),
                        'wednesday' => __('Wednesday'),
                        'thursday' => __('Thursday'),
                        'friday' => __('Friday'),
                    ])
                    ->required(),
                TimePicker::make('start_time')->seconds(false)->required()->label(__('Start Time')),
                TimePicker::make('end_time')->seconds(false)->required()->label(__('End Time')),
                Select::make('room_id')
                    ->label(__('Room'))
                    ->options(Room::query()->orderBy('number')->pluck('number', 'id'))
                    ->searchable()
                    ->preload(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('day')
            ->columns([
                TextColumn::make('day')->label(__('Day')),
                TextColumn::make('start_time')->label(__('Start')),
                TextColumn::make('end_time')->label(__('End')),
                TextColumn::make('room.number')->label(__('Room'))->placeholder('-'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
