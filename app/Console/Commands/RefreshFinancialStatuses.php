<?php

namespace App\Console\Commands;

use App\Services\FinancialDueService;
use Illuminate\Console\Command;

class RefreshFinancialStatuses extends Command
{
    protected $signature = 'finances:refresh {--dry-run : Show counts without saving}';

    protected $description = 'Recalculate financial_status (paid/due/overdue) for all registrations';

    public function handle(): int
    {
        if ($this->option('dry-run')) {
            $this->info('Dry-run mode — no changes will be saved.');
        }

        $this->info('Refreshing financial statuses…');

        $updated = FinancialDueService::refreshAllStatuses(dryRun: (bool) $this->option('dry-run'));

        $this->info("Done. {$updated} registration(s) updated.");

        return self::SUCCESS;
    }
}
