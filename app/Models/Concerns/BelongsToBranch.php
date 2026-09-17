<?php

namespace App\Models\Concerns;

use App\Models\Branch;
use App\Support\BranchContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Records that stand in one branch and are invisible from the others.
 *
 * The filtering is a global scope rather than something each screen remembers
 * to do, because "the employee only sees their branch" has to hold on the
 * screen nobody thought about as firmly as on the ones they did — a table, an
 * export, a select's options, a report's total. Anything that genuinely needs
 * the whole centre asks for it out loud with `withoutBranchScope()`.
 */
trait BelongsToBranch
{
    public static function bootBelongsToBranch(): void
    {
        static::addGlobalScope('branch', function (Builder $query): void {
            $branchId = BranchContext::currentBranchId();

            if ($branchId === null) {
                return;
            }

            $query->where($query->getModel()->getTable().'.branch_id', $branchId);
        });
    }

    /** The whole centre, for the few places that are allowed to look across it. */
    public function scopeWithoutBranchScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope('branch');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
