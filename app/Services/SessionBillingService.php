<?php

namespace App\Services;

use App\Models\Registration;
use App\Models\Section;
use App\Models\SectionSession;
use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Billing driven by the number of sessions actually held, as opposed to one
 * fixed price for the whole course.
 *
 * A section priced this way charges `cycle_fee` for every `sessions_per_cycle`
 * sessions it holds. Attendance is irrelevant: a session counts for every
 * enrolled student the moment it is marked as held, exactly as the spec
 * requires ("الحصة تحسب بمجرد تنفيذها للمجموعة"). Counting starts from the
 * session after the student joined (`session_offset`) and pauses while the
 * registration is paused.
 */
class SessionBillingService
{
    /** Sessions of grace before a "due" student is considered overdue. */
    public const OVERDUE_GRACE_SESSIONS = 2;

    /** How many sessions ahead of the payment the student gets warned. */
    public const WARNING_LEAD_SESSIONS = 2;

    /**
     * Number of counted (held, non-private) sessions a section has run.
     */
    public static function heldSessionCount(int $sectionId): int
    {
        return SectionSession::query()
            ->where('section_id', $sectionId)
            ->where('status', SectionSession::STATUS_HELD)
            ->where('counts_toward_billing', true)
            ->where('type', '!=', SectionSession::TYPE_PRIVATE)
            ->count();
    }

    /**
     * Bring a session's contribution to every student's counter in line with
     * its current state. Called from the session observer, so flipping a
     * session between held / cancelled / private adjusts the counters both ways.
     */
    public static function syncSession(SectionSession $session): void
    {
        $shouldCount = $session->advancesBillingCounter();
        $alreadyCounted = (bool) $session->counted_at_billing;

        if ($shouldCount === $alreadyCounted) {
            return;
        }

        $delta = $shouldCount ? 1 : -1;

        DB::transaction(function () use ($session, $delta, $shouldCount): void {
            foreach (self::countableRegistrations($session->section_id) as $registration) {
                $counted = max(0, (int) $registration->sessions_counted + $delta);
                $registration->forceFill(['sessions_counted' => $counted]);
                $registration->forceFill(['financial_status' => self::computeStatus($registration)])->saveQuietly();
            }

            $session->forceFill(['counted_at_billing' => $shouldCount])->saveQuietly();
        });
    }

    /**
     * Remove a deleted session's contribution from the counters.
     */
    public static function revertSession(SectionSession $session): void
    {
        if (! $session->counted_at_billing) {
            return;
        }

        DB::transaction(function () use ($session): void {
            foreach (self::countableRegistrations($session->section_id) as $registration) {
                $registration->forceFill(['sessions_counted' => max(0, (int) $registration->sessions_counted - 1)]);
                $registration->forceFill(['financial_status' => self::computeStatus($registration)])->saveQuietly();
            }

            $session->forceFill(['counted_at_billing' => false])->saveQuietly();
        });
    }

    /**
     * Registrations whose counter a session in this section should move:
     * per-session pricing, not paused, and belonging to a student who is not
     * suspended.
     *
     * @return Collection<int, Registration>
     */
    public static function countableRegistrations(int $sectionId)
    {
        return Registration::query()
            ->where('section_id', $sectionId)
            ->whereNull('paused_at')
            ->whereHas('section', fn ($q) => $q->where('fee_type', Section::FEE_TYPE_PER_SESSIONS))
            ->whereHas('student', fn ($q) => $q->where('status', 'active'))
            ->get();
    }

    /**
     * Sessions the student still has paid for. Negative once they owe.
     */
    public static function remainingSessions(Registration $registration): int
    {
        return (int) $registration->paid_through_session - (int) $registration->sessions_counted;
    }

    /**
     * ok | warning | due | overdue, based purely on the session counter.
     */
    public static function computeStatus(Registration $registration): string
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
     * Charge one (or more) payment cycles to a registration: extends the paid
     * horizon by `sessions_per_cycle` and raises the money fields, which the
     * registration observer turns into a wallet charge and the trainer's share.
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
     * Seed a brand-new per-session registration: counting starts after the
     * sessions the section already held, and any money paid up front buys
     * whole cycles.
     */
    public static function initializeRegistration(Registration $registration): void
    {
        $section = $registration->section ?: Section::find($registration->section_id);

        if (! $section || ! $section->isPerSessionBilled()) {
            return;
        }

        if (! $registration->session_offset) {
            $registration->session_offset = self::heldSessionCount($section->id);
        }

        $cycleFee = (float) $section->cycle_fee;
        $perCycle = (int) $section->sessions_per_cycle;

        if (! $registration->paid_through_session && $cycleFee > 0 && $perCycle > 0) {
            $cycles = (int) floor(((float) $registration->amount_paid) / $cycleFee);
            $registration->paid_through_session = $cycles * $perCycle;
        }
    }

    /**
     * Pause / resume counting for a registration (the student stops attending
     * for a while and picks up from the same point later).
     */
    public static function pause(Registration $registration): void
    {
        $registration->forceFill(['paused_at' => now()])->saveQuietly();
    }

    public static function resume(Registration $registration): void
    {
        $registration->forceFill(['paused_at' => null])->saveQuietly();
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
