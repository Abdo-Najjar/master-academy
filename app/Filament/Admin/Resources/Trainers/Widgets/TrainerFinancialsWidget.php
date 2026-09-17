<?php

namespace App\Filament\Admin\Resources\Trainers\Widgets;

use App\Models\Trainer;
use App\Support\TrainerFinancials;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

/**
 * The trainer's money in one line: what they earned, what they took, what is
 * still theirs — the three questions the desk is asked when a trainer walks in.
 *
 * Gated on `trainer.wallet`, the same permission that lets someone move money
 * on this page: reading the ledger and paying against it are one job.
 */
class TrainerFinancialsWidget extends BaseWidget
{
    public ?Trainer $record = null;

    protected int|string|array $columnSpan = 'full';

    protected int|array|null $columns = 4;

    public static function canView(): bool
    {
        return auth()->user()?->can('trainer.wallet') ?? false;
    }

    public function getHeading(): ?string
    {
        return __('Financial Summary');
    }

    protected function getStats(): array
    {
        if (! $this->record) {
            return [];
        }

        $totals = TrainerFinancials::for($this->record);

        return [
            Stat::make(__('Total Credited'), self::money($totals->credited))
                ->description(__('Teaching shares and desk deposits'))
                ->descriptionIcon('heroicon-m-arrow-down-tray')
                ->color('success'),

            Stat::make(__('Paid Out'), self::money($totals->paidOut))
                ->description(__('Taken out of the wallet'))
                ->descriptionIcon('heroicon-m-arrow-up-tray')
                ->color('warning'),

            Stat::make(__('Still Owed'), self::money($totals->balance))
                ->description(__('Wallet balance — credited minus paid out'))
                ->descriptionIcon('heroicon-m-wallet')
                ->color($totals->balance < 0 ? 'danger' : 'primary'),

            Stat::make(__('Awaiting Collection'), self::money($totals->pending))
                ->description(__('Share of charges students have not paid yet'))
                ->descriptionIcon('heroicon-m-clock')
                ->color($totals->pending > 0.009 ? 'danger' : 'gray'),
        ];
    }

    private static function money(float $amount): string
    {
        return Number::currency($amount, 'ILS', app()->getLocale());
    }
}
