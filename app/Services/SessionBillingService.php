<?php

namespace App\Services;

use App\Models\Registration;
use App\Models\Section;
use App\Models\SectionSession;
use App\Models\Student;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Billing driven by the number of sessions actually held, as opposed to one
 * fixed price for the whole course.
 *
 * A section priced this way charges `cycle_fee` for every `sessions_per_cycle`
 * sessions it holds. Whether the student showed up is irrelevant: a session
 * counts for every enrolled student the moment it is marked as held, exactly
 * as the spec requires ("الحصة تحسب بمجرد تنفيذها للمجموعة"). Two things take
 * a lesson back out of a student's bill:
 *
 * - it was held before they joined (`enrolled_at`), or inside a break they
 *   took (`registration_pauses`);
 * - they were marked as excused for it — an apology is not a charged lesson.
 *
 * `sessions_counted` is therefore *derived*: it is recomputed from the lessons
 * themselves rather than incremented as they happen. That is what makes
 * backdated data entry safe — a centre typing in three months of history
 * enters all its students first and all its lessons afterwards, and an
 * incremented counter would charge every one of those lessons to every one of
 * those students. Recomputing from dates gives the same answer whatever order
 * the rows arrive in, and makes "recalculate" a repeatable, side-effect-free
 * operation.
 */
class SessionBillingService
{
    /** Sessions of grace before a "due" student is considered overdue. */
    public const OVERDUE_GRACE_SESSIONS = 2;

    /** How many sessions ahead of the payment the student gets warned. */
    public const WARNING_LEAD_SESSIONS = 2;

    /** Attendance status that takes the lesson off the student's bill. */
    public const EXCUSED_STATUS = 'excused';

    /**
     * Set while a caller is still assembling the day's data. Taking attendance
     * creates the lesson *before* it writes the rows, so the session observer
     * would otherwise decide whether to charge without yet knowing who was
     * excused — and an excused student would be charged for a lesson that is
     * not theirs. `Attendance::recordDay()` holds the charge back until the
     * rows are in, then releases it.
     */
    protected static bool $autoChargeSuspended = false;

    /**
     * Run a callback with automatic cycle charging held back, then let the
     * caller charge once its own writes are complete.
     */
    public static function withoutAutoCharge(callable $callback): mixed
    {
        $previous = self::$autoChargeSuspended;
        self::$autoChargeSuspended = true;

        try {
            return $callback();
        } finally {
            self::$autoChargeSuspended = $previous;
        }
    }

    /**
     * Charge whatever payment cycles a registration has consumed but not paid
     * for. The counter reaching the paid horizon *is* the trigger: with
     * "100 ₪ every 8 lessons", the eighth recorded lesson takes the next
     * 100 ₪ off the student's wallet.
     *
     * Idempotent — `payCycle()` moves the paid horizon past the counter, so a
     * second call finds nothing outstanding.
     *
     * @return int cycles charged
     */
    public static function chargeDueCycles(Registration $registration): int
    {
        $section = $registration->relationLoaded('section')
            ? $registration->section
            : ($registration->section ?: Section::find($registration->section_id));

        if (! $section?->autoChargesCycles()) {
            return 0;
        }

        // A student who left owes nothing for cycles that start after them.
        if ($registration->left_at) {
            return 0;
        }

        // Nothing has been consumed yet, so there is no cycle to close. Without
        // this, a fresh registration sitting at 0 counted / 0 paid would be
        // charged the moment any session — even a private one that never counts
        // for them — touched the section.
        if ((int) $registration->sessions_counted <= 0) {
            return 0;
        }

        $perCycle = (int) $section->sessions_per_cycle;

        // Reaching the horizon exactly means the cycle is used up — the same
        // point at which `computeStatus()` calls the student "due".
        $outstanding = (int) $registration->sessions_counted - (int) $registration->paid_through_session;

        if ($outstanding < 0) {
            return 0;
        }

        $cycles = intdiv($outstanding, $perCycle) + 1;

        self::payCycle($registration, $cycles);

        return $cycles;
    }

    /**
     * Charge every per-session registration of a section that has run past its
     * paid cycle. Skipped while a caller is mid-write (see `withoutAutoCharge`).
     *
     * @return int cycles charged across the section
     */
    public static function chargeSectionDueCycles(int $sectionId): int
    {
        if (self::$autoChargeSuspended) {
            return 0;
        }

        $charged = 0;

        DB::transaction(function () use ($sectionId, &$charged): void {
            foreach (self::countableRegistrations($sectionId) as $registration) {
                $charged += self::chargeDueCycles($registration);
            }
        });

        return $charged;
    }

    /**
     * Number of counted (held, non-private) sessions a section has run.
     */
    public static function heldSessionCount(int $sectionId): int
    {
        return self::heldSessionsQuery($sectionId)->count();
    }

    /**
     * The same count, restricted to lessons held strictly before a given day —
     * the section's history as far as a student joining on that day is
     * concerned.
     */
    public static function heldSessionCountBefore(int $sectionId, string|CarbonInterface $date): int
    {
        return self::heldSessionsQuery($sectionId)
            ->whereDate('date', '<', self::asDate($date))
            ->count();
    }

    /** Held, billable, non-private lessons of a section. */
    protected static function heldSessionsQuery(int $sectionId): Builder
    {
        return SectionSession::query()
            ->where('section_id', $sectionId)
            ->where('status', SectionSession::STATUS_HELD)
            ->where('counts_toward_billing', true)
            ->where('type', '!=', SectionSession::TYPE_PRIVATE);
    }

    /**
     * The lessons this particular student is charged for: held by the section,
     * on or after the day they joined, outside every break they took, and not
     * one they were excused from.
     */
    public static function countedSessionsQuery(Registration $registration): Builder
    {
        $query = self::heldSessionsQuery((int) $registration->section_id)
            ->whereDate('date', '>=', $registration->enrolmentDate()->toDateString())
            // Leaving is final: the day they left is the first one they are no
            // longer charged for.
            ->when($registration->left_at, fn ($q) => $q->whereDate('date', '<', $registration->left_at->toDateString()))
            ->whereNotExists(fn ($sub) => $sub
                ->selectRaw('1')
                ->from('attendances')
                ->whereColumn('attendances.section_id', 'section_sessions.section_id')
                ->whereRaw('date(attendances.date) = date(section_sessions.date)')
                ->where('attendances.student_id', $registration->student_id)
                ->where('attendances.status', self::EXCUSED_STATUS));

        // A registration that has not been inserted yet cannot have breaks.
        if ($registration->exists) {
            $query->whereNotExists(fn ($sub) => $sub
                ->selectRaw('1')
                ->from('registration_pauses')
                ->where('registration_pauses.registration_id', $registration->getKey())
                ->whereRaw('date(registration_pauses.paused_from) <= date(section_sessions.date)')
                ->where(fn ($window) => $window
                    ->whereNull('registration_pauses.resumed_at')
                    ->orWhereRaw('date(registration_pauses.resumed_at) > date(section_sessions.date)')));
        }

        return $query;
    }

    /**
     * Recompute one registration's counter from the lessons themselves and
     * store the result. Idempotent by construction — running it twice, or
     * running it on data that was entered out of order, lands on the same
     * number.
     */
    public static function recount(Registration $registration): int
    {
        $section = $registration->relationLoaded('section')
            ? $registration->section
            : ($registration->section ?: Section::find($registration->section_id));

        if (! $section || ! $section->isPerSessionBilled()) {
            return (int) $registration->sessions_counted;
        }

        $counted = (int) $registration->sessions_carried_over
            + self::countedSessionsQuery($registration)->count();

        $registration->forceFill(['sessions_counted' => $counted]);
        $registration->forceFill(['financial_status' => self::computeStatus($registration)]);

        if ($registration->exists) {
            $registration->saveQuietly();
        }

        return $counted;
    }

    /**
     * Recompute every per-session registration of a section. This is what the
     * "recalculate" action runs after a batch of history has been typed in.
     *
     * @return int number of registrations recounted
     */
    public static function recountSection(int $sectionId): int
    {
        $registrations = self::countableRegistrations($sectionId);

        DB::transaction(function () use ($registrations): void {
            foreach ($registrations as $registration) {
                self::recount($registration);
            }
        });

        return $registrations->count();
    }

    /** Recompute every per-session registration a student holds. */
    public static function recountStudent(int $studentId): void
    {
        $registrations = Registration::query()
            ->where('student_id', $studentId)
            ->whereHas('section', fn ($q) => $q->where('fee_type', Section::FEE_TYPE_PER_SESSIONS))
            ->with('section')
            ->get();

        DB::transaction(function () use ($registrations): void {
            foreach ($registrations as $registration) {
                self::recount($registration);
            }
        });
    }

    /**
     * Bring a session's contribution to every student's counter in line with
     * its current state. Called from the session observer, so flipping a
     * session between held / cancelled / private adjusts the counters both ways.
     */
    public static function syncSession(SectionSession $session): void
    {
        $shouldCount = $session->advancesBillingCounter();

        DB::transaction(function () use ($session, $shouldCount): void {
            self::recountSection((int) $session->section_id);

            $session->forceFill(['counted_at_billing' => $shouldCount])->saveQuietly();
        });

        // A lesson that just happened may have used up somebody's cycle.
        self::chargeSectionDueCycles((int) $session->section_id);
    }

    /**
     * Remove a deleted session's contribution from the counters. The session is
     * already soft-deleted by the time this runs, so it drops out of the
     * recount on its own.
     */
    public static function revertSession(SectionSession $session): void
    {
        DB::transaction(function () use ($session): void {
            self::recountSection((int) $session->section_id);

            $session->forceFill(['counted_at_billing' => false])->saveQuietly();
        });
    }

    /**
     * Registrations a session in this section can move: those priced per
     * session. Whether a given lesson actually counts for them is decided per
     * lesson by `countedSessionsQuery()` — students who had not joined yet,
     * who were on a break, or who were excused simply do not match it.
     *
     * @return Collection<int, Registration>
     */
    public static function countableRegistrations(int $sectionId)
    {
        return Registration::query()
            ->where('section_id', $sectionId)
            ->whereHas('section', fn ($q) => $q->where('fee_type', Section::FEE_TYPE_PER_SESSIONS))
            ->with(['section', 'student'])
            ->get();
    }

    /**
     * Sessions the student still has paid for. Negative once they owe.
     */
    public static function remainingSessions(Registration $registration): int
    {
        return (int) $registration->paid_through_session - (int) $registration->sessions_counted;
    }

    /** Statuses from calmest to worst, so two readings can be compared. */
    protected const STATUS_SEVERITY = ['ok' => 0, 'warning' => 1, 'due' => 2, 'overdue' => 3];

    /**
     * ok | warning | due | overdue — the worse of two readings.
     *
     * The counter alone is not enough. It says whether the student has lessons
     * left, and charging a cycle moves it forward whether or not anybody paid
     * for that cycle: a section that charges its own cycles would raise a bill,
     * push the horizon past the counter and go straight back to "paid", while
     * the wallet sat at minus the cycle fee. Read the other way round, a student
     * who paid every bill can still be out of lessons.
     *
     * They are two different questions — "are there lessons left?" and "is the
     * bill covered?" — and the badge has room for one answer, so it gives the
     * one that needs attention first.
     */
    public static function computeStatus(Registration $registration): string
    {
        $counter = self::sessionStatus($registration);
        $money = self::moneyStatus($registration);

        return self::STATUS_SEVERITY[$money] > self::STATUS_SEVERITY[$counter] ? $money : $counter;
    }

    /** How many lessons the student has left on what has been billed. */
    protected static function sessionStatus(Registration $registration): string
    {
        $remaining = self::remainingSessions($registration);

        if ($remaining <= -self::OVERDUE_GRACE_SESSIONS) {
            return 'overdue';
        }

        if ($remaining <= 0) {
            return 'due';
        }

        if ($remaining <= self::WARNING_LEAD_SESSIONS) {
            return 'warning';
        }

        return 'ok';
    }

    /**
     * How much of what was billed is actually backed by money the student
     * handed over. One cycle behind is a bill waiting at the desk; more than a
     * cycle behind is a debt.
     */
    protected static function moneyStatus(Registration $registration): string
    {
        $unfunded = FinancialDueService::remainingBalance($registration);

        if ($unfunded <= 0.009) {
            return 'ok';
        }

        $section = $registration->relationLoaded('section')
            ? $registration->section
            : ($registration->section ?: Section::find($registration->section_id));

        $cycleFee = (float) ($section?->cycle_fee ?? 0);

        return ($cycleFee > 0 && $unfunded <= $cycleFee + 0.009) ? 'due' : 'overdue';
    }

    /**
     * Charge one (or more) payment cycles to a registration: extends the paid
     * horizon by `sessions_per_cycle` and raises the money fields, which the
     * registration observer turns into a wallet charge and the trainer's share.
     * The share itself is re-derived from the new `amount_paid` by the observer,
     * so collecting more cycles keeps the trainer on their percentage instead of
     * diluting them down to a share of the first cycle.
     */
    public static function payCycle(Registration $registration, int $cycles = 1, ?float $amount = null): void
    {
        $registration->loadMissing('section');
        $section = $registration->section;

        if (! $section || ! $section->isPerSessionBilled()) {
            return;
        }

        $cycles = max(1, $cycles);
        $fee = $amount ?? round((float) $section->cycle_fee * $cycles, 2);
        $sessions = (int) $section->sessions_per_cycle * $cycles;

        $registration->amount_due = round((float) $registration->amount_due + $fee, 2);
        $registration->amount_paid = round((float) $registration->amount_paid + $fee, 2);
        $registration->paid_through_session = (int) $registration->paid_through_session + $sessions;
        $registration->save();
    }

    /**
     * Seed a brand-new per-session registration: the section's lessons before
     * the student's enrolment date are its own history, the ones after it are
     * already on the student's bill (a backdated enrolment is charged for the
     * lessons that were entered before the registration was), and any money
     * paid up front buys whole cycles.
     */
    public static function initializeRegistration(Registration $registration): void
    {
        $section = $registration->section ?: Section::find($registration->section_id);

        if (! $section || ! $section->isPerSessionBilled()) {
            return;
        }

        if (! $registration->session_offset) {
            $registration->session_offset = self::heldSessionCountBefore($section->id, $registration->enrolmentDate());
        }

        $cycleFee = (float) $section->cycle_fee;
        $perCycle = (int) $section->sessions_per_cycle;

        if (! $registration->paid_through_session && $cycleFee > 0 && $perCycle > 0) {
            $cycles = (int) floor(((float) $registration->amount_paid) / $cycleFee);
            $registration->paid_through_session = $cycles * $perCycle;
        }

        $registration->sessions_counted = (int) $registration->sessions_carried_over
            + self::countedSessionsQuery($registration)->count();
    }

    /**
     * Stop counting for a registration from a given day (today unless the
     * break is being recorded after the fact). Lessons held from that day on
     * stop being charged until the student comes back.
     */
    public static function pause(Registration $registration, string|CarbonInterface|null $from = null, ?string $reason = null): void
    {
        $from = self::asDate($from ?? now());

        DB::transaction(function () use ($registration, $from, $reason): void {
            $open = $registration->pauses()->open()->first();

            if ($open) {
                $open->update(['paused_from' => $from, 'reason' => $reason ?? $open->reason]);
            } else {
                $registration->pauses()->create([
                    'paused_from' => $from,
                    'reason' => $reason,
                ]);
            }

            $registration->forceFill(['paused_at' => Carbon::parse($from)->startOfDay()])->saveQuietly();

            self::recount($registration);
        });
    }

    /**
     * Resume counting from a given day. Lessons held during the break stay off
     * the bill for good — the window is kept, so a later recalculation does not
     * quietly hand them back.
     */
    public static function resume(Registration $registration, string|CarbonInterface|null $on = null): void
    {
        $on = self::asDate($on ?? now());

        DB::transaction(function () use ($registration, $on): void {
            $registration->pauses()->open()->update(['resumed_at' => $on]);

            $registration->forceFill(['paused_at' => null])->saveQuietly();

            self::recount($registration);
        });
    }

    /** Normalise whatever the caller passed into a plain `Y-m-d` string. */
    protected static function asDate(string|CarbonInterface $date): string
    {
        return $date instanceof CarbonInterface
            ? $date->toDateString()
            : Carbon::parse($date)->toDateString();
    }

    /**
     * Charge a private session's own fee to the given students and credit the
     * trainer with the session's own percentage. Private lessons are billed on
     * their own and never touch the regular counter.
     *
     * @param  array<int, int>  $studentIds
     * @return int number of students charged
     */
    public static function chargePrivateSession(SectionSession $session, array $studentIds): int
    {
        if (! $session->isPrivate() || (float) $session->fee <= 0) {
            return 0;
        }

        $session->loadMissing(['section.trainer', 'section.subject', 'section.branch']);
        $trainer = $session->section?->trainer;
        $fee = (float) $session->fee;
        $rate = $session->effectiveTrainerRate();
        $trainerShare = round($fee * $rate / 100, 2);
        $charged = 0;

        // Spell out date, course, section and branch, so the wallet statement
        // says exactly which lesson the money was for.
        $locale = app()->getLocale();
        $sessionLabel = collect([
            __('Private Session').' — '.$session->date?->toDateString(),
            $session->section?->subject?->getTranslation('name', $locale, false)
                ? __('Course').': '.$session->section->subject->getTranslation('name', $locale, false)
                : null,
            $session->section?->name ? __('Section').': '.$session->section->name : null,
            $session->section?->branch?->name ? __('Branch').': '.$session->section->branch->name : null,
        ])->filter()->implode(' · ');

        DB::transaction(function () use ($session, $studentIds, $fee, $trainerShare, $trainer, $sessionLabel, &$charged): void {
            foreach (Student::query()->whereIn('id', $studentIds)->get() as $student) {
                $student->forceWithdrawFloat($fee, [
                    'description' => __('Private session fee: :name', ['name' => $session->section?->name ?? '#'.$session->section_id]),
                    'note' => $sessionLabel,
                    'section_session_id' => $session->id,
                ]);

                if ($trainer && $trainerShare > 0) {
                    $trainer->depositFloat($trainerShare, [
                        'description' => __('Trainer share from private session: :name', ['name' => $session->section?->name ?? '#'.$session->section_id]),
                        'note' => $sessionLabel,
                        'section_session_id' => $session->id,
                    ]);
                }

                $charged++;
            }
        });

        return $charged;
    }
}
