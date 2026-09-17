<?php

namespace App\Filament\Admin\Resources\Sections\Widgets;

use App\Models\Section;
use App\Support\SectionFinancials;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

/**
 * The section's money in one line: what it is owed, what came in, what is left.
 *
 * Gated on `registration.index` rather than `section.index` — the figures are
 * sums over registrations, so anyone who cannot open a registration has no
 * business reading its money from here either.
 */
class SectionFinancialsWidget extends BaseWidget
{
    public ?Section $record = null;

    protected int|string|array $columnSpan = 'full';

    protected int|array|null $columns = 3;

    public static function canView(): bool
    {
        return auth()->user()?->can('registration.index') ?? false;
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

        $totals = SectionFinancials::for($this->record);

        return [
            Stat::make(__('Expected'), self::money($totals->expected))
                ->description($this->expectedCaption($totals))
                ->descriptionIcon('heroicon-m-receipt-percent')
                ->color('primary'),

            Stat::make(__('Collected'), self::money($totals->collected))
                ->description(__(':percent% of what was billed', [
                    'percent' => Number::format($totals->collectionRate(), locale: app()->getLocale()),
                ]))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make(__('Outstanding'), self::money($totals->outstanding))
                ->description(__('Still to collect from students'))
                ->descriptionIcon('heroicon-m-exclamation-circle')
                ->color($totals->outstanding > 0.009 ? 'danger' : 'success'),

            Stat::make(__('Trainer Share'), self::money($totals->trainerShare))
                ->description(__('Credited so far').': '.self::money($totals->trainerCredited))
                ->descriptionIcon('heroicon-m-user')
                ->color('warning'),

            Stat::make(__('Net to the Centre'), self::money($totals->net()))
                ->description(__('Collected minus the trainer share already credited'))
                ->descriptionIcon('heroicon-m-building-library')
                ->color('info'),

            Stat::make(__('Exemptions / Discounts'), self::money($totals->exemptions))
                ->description(trans_choice(':count registration|:count registrations', $totals->registrations, [
                    'count' => Number::format($totals->registrations, locale: app()->getLocale()),
                ]))
                ->descriptionIcon('heroicon-m-gift')
                ->color('gray'),
        ];
    }

    /**
     * A fixed-course section has billed its whole price already, so "expected"
     * is final. A per-session one bills a cycle at a time and the figure climbs
     * with the course — said out loud, so nobody reads it as a course total.
     */
    private function expectedCaption(SectionFinancials $totals): string
    {
        if ($this->record?->isPerSessionBilled()) {
            return __('Billed so far — grows with every cycle charged');
        }

        return $totals->exemptions > 0.009
            ? __('Course fees after :amount in exemptions', ['amount' => self::money($totals->exemptions)])
            : __('Course fees charged to enrolled students');
    }

    private static function money(float $amount): string
    {
        return Number::currency($amount, 'ILS', app()->getLocale());
    }
}
