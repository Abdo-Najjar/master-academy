<?php

namespace App\Filament\Support;

use App\Models\Branch;
use App\Support\BranchContext;
use Filament\Forms\Components\Select;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Model;

/**
 * "Which site is this?" — asked the same way on every screen that owns a record.
 *
 * The options are whatever the person at the screen is allowed to see, so an
 * employee tied to one branch cannot file a room, a course or an expense under
 * another one: their branch is the only thing in the list, and it is already
 * chosen. Someone who runs the whole centre gets the full list and has to say
 * which site they mean.
 */
class BranchField
{
    /**
     * The "no branch at all" choice on the employee form. Zero rather than
     * null because a Select cannot carry null as an option value, and no branch
     * will ever have id 0.
     */
    public const HEAD_OFFICE = 0;

    public static function make(string $name = 'branch_id', bool $required = true): Select
    {
        return Select::make($name)
            ->label(__('Branch'))
            ->options(fn (): array => BranchContext::selectableBranches())
            ->default(fn (): ?int => BranchContext::defaultBranchId())
            ->searchable()
            ->preload()
            ->native(false)
            // Required only once there is something to choose. A centre that
            // has not set its branches up yet would otherwise be unable to save
            // a room or a course at all: the field would demand an answer from
            // an empty list.
            ->required(fn (): bool => $required && Branch::query()->exists());
    }

    /**
     * "Which sites does this person work at?" — the same question asked of
     * someone who is not tied to one place.
     *
     * A trainer teaches mornings at one branch and evenings at another, so
     * theirs is a list rather than a choice. The options are still only what the
     * person at the screen may see, and an employee tied to a branch gets their
     * own filled in already: they cannot enrol a trainer at another site, and
     * having to pick the only option there is would be busywork.
     */
    public static function multiple(string $relationship = 'branches', bool $required = true): Select
    {
        return Select::make($relationship)
            ->label(__('Branches'))
            ->multiple()
            ->relationship(
                name: $relationship,
                titleAttribute: 'name',
                modifyQueryUsing: fn ($query) => $query->when(
                    BranchContext::currentBranchId(),
                    fn ($restricted, int $branchId) => $restricted->whereKey($branchId),
                ),
            )
            // The branch name is translatable, so it has to be read off the
            // record rather than plucked straight out of the column — a raw
            // pluck hands back the whole JSON document as the label.
            ->getOptionLabelFromRecordUsing(fn (Branch $record): string => $record->name)
            ->default(fn (): array => array_filter([BranchContext::defaultBranchId()]))
            ->searchable()
            ->preload()
            ->native(false)
            ->required(fn (): bool => $required && Branch::query()->exists());
    }

    /**
     * The employee's own branch box, which has one option the others do not:
     * "head office", meaning no branch at all and therefore the whole centre.
     *
     * It is still required — nobody gets a branch by forgetting to pick one —
     * but seeing everything is now something you can deliberately choose rather
     * than something you could only get by leaving a field blank.
     *
     * The head-office option is offered only to someone who already sees the
     * whole centre. A branch manager handing out an account that reads every
     * other site would be handing out more than they hold themselves.
     */
    public static function forEmployee(string $name = 'branch_id'): Select
    {
        return static::make($name)
            ->options(function (): array {
                $branches = BranchContext::selectableBranches();

                return BranchContext::isRestricted()
                    ? $branches
                    : [self::HEAD_OFFICE => __('All Branches (head office)')] + $branches;
            })
            // An existing employee with no branch reads back as head office
            // rather than as an empty box. A *new* one starts empty on purpose:
            // "sees everything" should never be the default nobody chose.
            ->afterStateHydrated(function (Select $component, mixed $state, ?Model $record): void {
                if ($record !== null && $state === null) {
                    $component->state(self::HEAD_OFFICE);
                }
            })
            ->dehydrateStateUsing(fn (mixed $state): mixed => (int) $state === self::HEAD_OFFICE ? null : $state);
    }

    /**
     * The matching table filter. It disappears for an employee tied to a
     * branch: every row they can see is already theirs, so a filter with one
     * option is a control that does nothing.
     */
    public static function filter(string $name = 'branch_id'): SelectFilter
    {
        return SelectFilter::make($name)
            ->label(__('Branch'))
            ->options(fn (): array => BranchContext::selectableBranches())
            ->preload()
            ->visible(fn (): bool => ! BranchContext::isRestricted());
    }
}
