<?php

use App\Services\FinancialDueService;
use Illuminate\Database\Migrations\Migration;

/**
 * Rewrite the stored `financial_status` of every registration.
 *
 * The status of a per-session course used to be read off the session counter
 * alone. Charging a cycle pushes that counter forward whether or not anybody
 * paid, so a course could sit on "paid" while its share of the wallet was
 * negative — visible on the student's own screen as a green badge next to a
 * remaining balance. The rule now takes the unfunded part of the bill into
 * account as well, but the column is stored, so rows written under the old rule
 * keep showing the old answer until they are recomputed. This does that once.
 *
 * Safe to run on live data, and safe to run twice:
 *
 * - it writes one derived column and nothing else. No wallet movement, no
 *   charge, no cycle, no trainer share.
 * - it goes through `updateQuietly()`, so no observer fires and no activity log
 *   entry is written.
 * - the value is recomputed from the row's own money and counters, so running
 *   it again lands on the same answer.
 *
 * Run `php artisan finances:refresh --dry-run` first to see how many rows it
 * would change without touching anything.
 */
return new class extends Migration
{
    public function up(): void
    {
        FinancialDueService::refreshAllStatuses();
    }

    /**
     * Nothing to undo. The old values were wrong on exactly the rows this
     * corrects, and they are derived — rolling back would mean deliberately
     * restoring a badge that contradicts the money next to it.
     */
    public function down(): void
    {
        //
    }
};
