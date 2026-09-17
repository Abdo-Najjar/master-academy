<?php

namespace App\Support;

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Expense;
use App\Models\Registration;
use App\Models\RoomBooking;
use App\Models\Section;
use App\Models\SectionSession;
use App\Models\Student;
use App\Models\Trainer;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * "Which branch is the person at the screen allowed to see?"
 *
 * One answer, asked from everywhere — the model scopes, the forms, the filters
 * and the reports — so a branch can never be walled off on one screen and left
 * open on the next.
 *
 * Two people are not restricted: an employee with no branch, who is head
 * office, and the super admin, who is exempt whatever their branch says. The
 * second rule exists so that assigning the owner to a site cannot quietly hide
 * the rest of the centre from the only account that can put it back.
 */
class BranchContext
{
    /** The branch the signed-in employee is confined to, or null for all of them. */
    public static function currentBranchId(): ?int
    {
        // The admin guard only. A trainer or a student signing into their own
        // portal is a different model on a different guard, and none of this
        // applies to them.
        $user = auth('web')->user();

        if (! $user instanceof User || $user->isSuperAdmin()) {
            return null;
        }

        return $user->branch_id ? (int) $user->branch_id : null;
    }

    /** Is the person at the screen confined to one branch? */
    public static function isRestricted(): bool
    {
        return self::currentBranchId() !== null;
    }

    /**
     * The branches this person may pick from: their own, or all of them.
     *
     * @return array<int, string> branch id => name
     */
    public static function selectableBranches(): array
    {
        $branchId = self::currentBranchId();

        return Branch::query()
            ->when($branchId, fn ($query) => $query->whereKey($branchId))
            ->get()
            ->mapWithKeys(fn (Branch $branch): array => [$branch->id => $branch->name])
            ->all();
    }

    /**
     * What a new record's branch should start as: the employee's own branch
     * when they have one, so the desk never has to say where it is standing.
     */
    public static function defaultBranchId(): ?int
    {
        return self::currentBranchId();
    }

    /**
     * Narrow the activity log to the branch, if there is one.
     *
     * The log is polymorphic, so there is nothing to join on: what it holds is
     * a class name and an id. The rule is therefore stated per class — an entry
     * about something branch-scoped is only shown when its subject is one this
     * employee can still see, which the models' own scopes already decide. An
     * entry about anything else (a student, a trainer, a payment type) is
     * shared, and so is its history.
     *
     * @param  Builder<covariant \Spatie\Activitylog\Models\Activity>  $query
     */
    public static function scopeActivityLog(mixed $query): mixed
    {
        if (self::currentBranchId() === null) {
            return $query;
        }

        /** @var list<class-string<Model>> */
        $scoped = [
            Section::class,
            Registration::class,
            Attendance::class,
            SectionSession::class,
            Expense::class,
            RoomBooking::class,
        ];

        return $query->where(function ($outer) use ($scoped): void {
            $outer
                ->whereNull('subject_type')
                ->orWhereNotIn('subject_type', $scoped);

            foreach ($scoped as $class) {
                // The subquery carries the model's own branch scope, so this
                // never has to restate what "my branch" means.
                $outer->orWhere(fn ($q) => $q
                    ->where('subject_type', $class)
                    ->whereIn('subject_id', $class::query()->select((new $class)->getQualifiedKeyName())));
            }
        });
    }

    /**
     * Narrow the login history to the branch, if there is one.
     *
     * Students and trainers sign in across the whole centre and their history
     * is shared with it. Employees are not: an employee tied to a branch does
     * not see the staff of another site on the staff screen, and seeing their
     * names, addresses and devices here instead would simply be the same list
     * by another route.
     *
     * @param  Builder<covariant \App\Models\LoginActivity>  $query
     */
    public static function scopeLoginActivities(mixed $query): mixed
    {
        $branchId = self::currentBranchId();

        if ($branchId === null) {
            return $query;
        }

        return $query->where(fn ($outer) => $outer
            ->where('auth_type', '!=', User::class)
            ->orWhereIn('auth_id', User::query()
                ->select('users.id')
                ->where('users.branch_id', $branchId)));
    }

    /**
     * Narrow a wallet-movement query to the branch, if there is one.
     *
     * Wallets are the one part of the money that has no branch of its own: a
     * student belongs to the whole centre by design, and their balance is a
     * single figure however many sites they study at. So "whose money is this"
     * is answered through the sections — a movement belongs to a branch when
     * the student it is for studies there, or the trainer it is for teaches
     * there. A student enrolled at two sites shows up in both, which is the
     * honest answer: both desks handled their money.
     *
     * @param  Builder<covariant \Bavix\Wallet\Models\Transaction>  $query
     */
    public static function scopeWalletTransactions(mixed $query): mixed
    {
        $branchId = self::currentBranchId();

        if ($branchId === null) {
            return $query;
        }

        return $query->where(fn ($outer) => $outer
            // Money the desk stamped as its own when it took it. This is the
            // reliable answer and the only one that works for a student who is
            // not enrolled anywhere yet — an advance handed over before they
            // pick a course belongs to the branch that took it, not to nobody.
            ->where('meta->branch_id', $branchId)
            // Everything recorded before movements carried a branch, and
            // everything raised automatically with no one signed in, is placed
            // by where the person it concerns actually studies or teaches.
            ->orWhere(fn ($untagged) => $untagged
                ->whereNull('meta->branch_id')
                ->where(fn ($who) => $who
                    ->where(fn ($asStudent) => $asStudent
                        ->where('payable_type', Student::class)
                        ->whereIn('payable_id', fn ($sub) => $sub
                            ->select('registrations.student_id')
                            ->from('registrations')
                            ->join('sections', 'sections.id', '=', 'registrations.section_id')
                            ->whereNull('registrations.deleted_at')
                            ->where('sections.branch_id', $branchId)))
                    ->orWhere(fn ($asTrainer) => $asTrainer
                        ->where('payable_type', Trainer::class)
                        ->whereIn('payable_id', fn ($sub) => $sub
                            ->select('sections.trainer_id')
                            ->from('sections')
                            ->whereNull('sections.deleted_at')
                            ->where('sections.branch_id', $branchId))))));
    }
}
