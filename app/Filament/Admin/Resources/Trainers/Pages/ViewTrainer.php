<?php

namespace App\Filament\Admin\Resources\Trainers\Pages;

use App\Filament\Admin\Resources\Trainers\Actions\WalletActions;
use App\Filament\Admin\Resources\Trainers\TrainerResource;
use App\Filament\Admin\Resources\Trainers\Widgets\TrainerEarningsWidget;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewTrainer extends ViewRecord
{
    protected static string $resource = TrainerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                WalletActions::deposit(),
                WalletActions::withdraw(),
                EditAction::make(),
            ])
                ->label(__('Actions'))
                ->icon('heroicon-o-ellipsis-vertical')
                ->button(),
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            TrainerEarningsWidget::class,
        ];
    }
}
