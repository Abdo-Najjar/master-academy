<?php

namespace App\Models\Concerns;

use App\Support\BranchContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Records with no branch of their own that borrow their section's.
 *
 * A registration, a lesson, an attendance mark — none of them names a branch,
 * but each belongs to exactly one section and the section names one. A grade is
 * a step further out: it belongs to an exam, and the exam belongs to a section.
 * `branchPathToSection()` is how a model says which of those it is.
 *
 * The check is a bare `EXISTS` rather than `whereHas('section')` on purpose:
 * `whereHas` would drag in the section's own global scopes, and the soft-delete
 * one among them would silently drop every row whose section was deleted —
 * which is a different rule than "not my branch", and one this trait has no
 * business enforcing.
 */
trait BelongsToBranchThroughSection
{
    /**
     * The walk from this row to the section that owns it: each step is the
     * table to step into, keyed to the column on the *previous* step that
     * points at it. Most rows point at a section directly.
     *
     * @return array<string, string> table => foreign key on the step before it
     */
    protected static function branchPathToSection(): array
    {
        return ['sections' => 'section_id'];
    }

    public static function bootBelongsToBranchThroughSection(): void
    {
        static::addGlobalScope('branch', function (Builder $query): void {
            $branchId = BranchContext::currentBranchId();

            if ($branchId === null) {
                return;
            }

            $table = $query->getModel()->getTable();
            $path = static::branchPathToSection();

            $query->whereExists(function ($sub) use ($path, $table, $branchId): void {
                $steps = $path;
                $firstTable = (string) array_key_first($steps);
                $firstKey = array_shift($steps);

                // The row is tied to the first step; every step after that is
                // joined on, until the walk arrives at a section.
                $sub->select(DB::raw(1))
                    ->from($firstTable)
                    ->whereColumn($firstTable.'.id', $table.'.'.$firstKey);

                $previous = $firstTable;

                foreach ($steps as $nextTable => $foreignKey) {
                    $sub->join($nextTable, $nextTable.'.id', '=', $previous.'.'.$foreignKey);
                    $previous = $nextTable;
                }

                $sub->where('sections.branch_id', $branchId);
            });
        });
    }

    /** The whole centre, for the few places that are allowed to look across it. */
    public function scopeWithoutBranchScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope('branch');
    }
}
