<?php

/**
 * End-to-end rehearsal of the day the centre puts its paper registers into the
 * system: three months of lessons, entered *after* every student already
 * exists, with students who joined at different points, one who took a break
 * and one who apologised for a few lessons.
 *
 * The point is not that each piece works — the other tests cover that — but
 * that the whole entry order the centre will actually use produces the numbers
 * that are on the paper.
 */

use App\Models\Attendance;
use App\Models\Registration;
use App\Models\Section;
use App\Models\SectionSession;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Trainer;
use App\Services\SectionWithdrawalService;
use App\Services\SessionBillingService;
use Carbon\Carbon;

/** Every lesson day of the register: Saturdays and Tuesdays, June–August. */
function registerDates(): array
{
    $dates = [];
    $day = Carbon::parse('2026-06-01');
    $end = Carbon::parse('2026-08-31');

    while ($day->lte($end)) {
        if (in_array($day->dayOfWeek, [Carbon::SATURDAY, Carbon::TUESDAY], true)) {
            $dates[] = $day->toDateString();
        }
        $day = $day->addDay();
    }

    return $dates;
}

beforeEach(function () {
    $this->trainer = Trainer::create([
        'name' => ['ar' => 'أ. محمد', 'en' => 'Mr. Mohammed'],
        'username' => 'history_trainer_'.uniqid(),
        'password' => 'password',
        'default_rate' => 50,
    ]);

    $this->subject = Subject::create(['name' => ['ar' => 'رياضيات', 'en' => 'Maths']]);

    // 100 ₪ every 8 lessons held. Cycles are collected by hand here: this
    // rehearsal is about the counters landing on the numbers written on the
    // paper register, and the payments are entered from the receipts.
    // Automatic charging is exercised in PerSessionAutoChargeTest.
    $this->section = Section::create([
        'name' => 'شعبة الرياضيات - صباحي',
        'subject_id' => $this->subject->id,
        'trainer_id' => $this->trainer->id,
        'price' => 0,
        'fee_type' => Section::FEE_TYPE_PER_SESSIONS,
        'sessions_per_cycle' => 8,
        'cycle_fee' => 100,
        'auto_charge_cycles' => false,
        'start_date' => '2026-06-01',
    ]);
});

it('enters three months of paper registers and bills every student correctly', function () {
    $dates = registerDates();

    // ── Step 1: the secretary types in every student first, in one sitting,
    // each with the day they actually joined written on the register.
    // name => [joined on, cash handed over for the whole three months]
    $roster = [
        'أحمد' => ['2026-06-01', 400], // from the very first lesson
        'سارة' => ['2026-07-15', 200], // joined mid-course
        'خالد' => ['2026-08-05', 100], // joined a few weeks ago
        'ليلى' => ['2026-06-01', 300], // from the start, but took a break
        'يوسف' => ['2026-06-01', 300], // from the start, apologised three times
    ];

    $registrations = [];

    foreach ($roster as $name => [$joinedOn, $cash]) {
        $student = Student::create([
            'name' => ['ar' => $name, 'en' => $name],
            'username' => 'hist_'.uniqid(),
            'password' => 'password',
            'status' => 'active',
            'enrolled_at' => $joinedOn,
        ]);

        // The cash goes on the wallet before anything is charged to it, the
        // same order the enrollment screen uses.
        $student->depositFloat($cash, ['description' => 'دفعات ثلاثة شهور']);

        // The first cycle (8 lessons) is charged at registration.
        $registrations[$name] = Registration::create([
            'student_id' => $student->id,
            'section_id' => $this->section->id,
            'enrolled_at' => $joinedOn,
            'amount_due' => 100,
            'amount_paid' => 100,
        ]);
    }

    // ── Step 2: three months of attendance, entered afterwards, day by day,
    // exactly as the pages of the register are worked through.
    $excusedDates = ['2026-06-16', '2026-07-07', '2026-08-11']; // يوسف apologised
    $absentDates = ['2026-06-09', '2026-07-21'];                 // أحمد was absent

    foreach ($dates as $date) {
        $statuses = [];

        foreach ($roster as $name => [$joinedOn, $cash]) {
            $studentId = $registrations[$name]->student_id;

            // The secretary ticks the whole column, including students who had
            // not joined yet — the system is what has to know better.
            $statuses[$studentId] = match (true) {
                $name === 'يوسف' && in_array($date, $excusedDates, true) => 'excused',
                $name === 'أحمد' && in_array($date, $absentDates, true) => 'absent',
                // ليلى stopped coming for a month.
                $name === 'ليلى' && $date >= '2026-07-10' && $date < '2026-08-10' => 'absent',
                default => 'present',
            };
        }

        Attendance::recordDay($this->section->id, $date, $statuses);
    }

    // ── Step 3: the break ليلى took is recorded after the fact.
    SessionBillingService::pause($registrations['ليلى']->fresh(), '2026-07-10', 'سفر');
    SessionBillingService::resume($registrations['ليلى']->fresh(), '2026-08-10');

    // ── What the paper says, counted by hand ─────────────────────────────────
    $totalLessons = count($dates);
    $expected = [
        'أحمد' => count($dates),
        'سارة' => count(array_filter($dates, fn ($d): bool => $d >= '2026-07-15')),
        'خالد' => count(array_filter($dates, fn ($d): bool => $d >= '2026-08-05')),
        'ليلى' => count(array_filter($dates, fn ($d): bool => $d < '2026-07-10' || $d >= '2026-08-10')),
        'يوسف' => count($dates) - count($excusedDates),
    ];

    foreach ($expected as $name => $shouldBe) {
        expect($registrations[$name]->fresh()->sessions_counted)
            ->toBe($shouldBe, "عدد الحصص المحتسبة على {$name}");
    }

    // Attendance rows only exist for students who had joined.
    foreach ($roster as $name => [$joinedOn, $cash]) {
        $rows = Attendance::query()
            ->where('student_id', $registrations[$name]->student_id)
            ->get();

        expect($rows)->toHaveCount(count(array_filter($dates, fn ($d): bool => $d >= $joinedOn)),
            "عدد سجلات الحضور لـ {$name}");

        expect($rows->filter(fn (Attendance $a): bool => $a->date->toDateString() < $joinedOn))
            ->toBeEmpty("سجلات حضور قبل التحاق {$name}");
    }

    // ── Step 4: the recalculate button. Running it must change nothing.
    $before = collect($registrations)->map(fn (Registration $r): int => $r->fresh()->sessions_counted);
    SessionBillingService::recountSection($this->section->id);
    $after = collect($registrations)->map(fn (Registration $r): int => $r->fresh()->sessions_counted);

    expect($after->all())->toBe($before->all());

    // ── Step 5: the three months of payments are collected. Each student had
    // one cycle charged at registration, so only the rest is taken now.
    foreach ($roster as $name => [$joinedOn, $cash]) {
        $cycles = (int) ($cash / 100) - 1;

        if ($cycles > 0) {
            SessionBillingService::payCycle($registrations[$name]->fresh(), $cycles);
        }
    }

    // Every wallet is square: they paid exactly what the lessons came to.
    foreach ($roster as $name => [$joinedOn, $cash]) {
        $registration = $registrations[$name]->fresh();

        expect((float) $registration->student->balanceFloat)
            ->toBe(0.0, "رصيد محفظة {$name}")
            ->and((float) $registration->amount_paid)
            ->toBe((float) $cash, "إجمالي ما دفعه {$name}");
    }

    // The trainer earned their 50% of everything collected.
    expect((float) $this->trainer->fresh()->balanceFloat)
        ->toBe((float) array_sum(array_column($roster, 1)) / 2);

    // ── The report the operator would want to read ───────────────────────────
    $pad = fn (string $value, int $width): string => mb_str_pad($value, $width);

    $lines = [];
    $lines[] = 'الشعبة: '.$this->section->name;
    $lines[] = 'التسعير: '.number_format((float) $this->section->cycle_fee, 0).' ₪ لكل '.$this->section->sessions_per_cycle.' حصص';
    $lines[] = 'عدد الحصص المنعقدة بين '.$dates[0].' و '.end($dates).': '.$totalLessons;
    $lines[] = '';
    $lines[] = $pad('الطالب', 10).$pad('التحق في', 14).$pad('محتسب', 9).$pad('مدفوع', 9).$pad('المتبقي', 10).$pad('دفع ₪', 9).'الحالة';
    $lines[] = str_repeat('-', 70);

    foreach ($roster as $name => [$joinedOn, $cash]) {
        $r = $registrations[$name]->fresh();
        $lines[] = $pad($name, 10)
            .$pad($joinedOn, 14)
            .$pad((string) $r->sessions_counted, 9)
            .$pad((string) $r->paid_through_session, 9)
            .$pad((string) $r->remainingSessions(), 10)
            .$pad(number_format((float) $r->amount_paid, 0), 9)
            .$r->financial_status;
    }

    $lines[] = '';
    $lines[] = 'حصص اعتذر عنها يوسف: '.implode(' · ', $excusedDates);
    $lines[] = 'انقطاع ليلى: من 2026-07-10 حتى 2026-08-10';
    $lines[] = 'جلسات أُنشئت في section_sessions: '.SectionSession::query()->where('section_id', $this->section->id)->count();
    $lines[] = 'سجلات حضور: '.Attendance::query()->where('section_id', $this->section->id)->count();
    $lines[] = 'رصيد المدرب (50%): '.number_format((float) $this->trainer->fresh()->balanceFloat, 2).' ₪';

    file_put_contents(
        base_path('storage/logs/backdated-entry-report.txt'),
        implode(PHP_EOL, $lines).PHP_EOL,
    );
});

/**
 * The other pattern the paper registers show: a full-size group where a number
 * of students simply stop coming partway through and their row turns into a
 * run of crosses to the end of the page. They must stop being billed from the
 * day they stopped, while everything they did attend stays on the record.
 */
it('stops billing students who left partway through a full-size group', function () {
    $dates = registerDates();

    // 27 students, as on the register.
    $stoppedOn = [
        3 => '2026-06-30',
        7 => '2026-07-14',
        12 => '2026-08-01',
        20 => '2026-07-01',
    ];

    $joinedOn = [
        18 => '2026-07-07', // a few joined after the group had started
        21 => '2026-07-28',
        25 => '2026-08-08',
    ];

    $registrations = [];

    for ($i = 1; $i <= 27; $i++) {
        $student = Student::create([
            'name' => ['ar' => 'طالب '.$i, 'en' => 'Student '.$i],
            'username' => 'big_'.$i.'_'.uniqid(),
            'password' => 'password',
            'status' => 'active',
        ]);

        $registrations[$i] = Registration::create([
            'student_id' => $student->id,
            'section_id' => $this->section->id,
            'enrolled_at' => $joinedOn[$i] ?? '2026-06-01',
            'amount_due' => 100,
            'amount_paid' => 100,
        ]);
    }

    // The whole three months, entered afterwards. Students who stopped keep
    // getting a cross on the paper, so they keep getting one here too.
    foreach ($dates as $date) {
        $statuses = [];

        for ($i = 1; $i <= 27; $i++) {
            $statuses[$registrations[$i]->student_id] =
                isset($stoppedOn[$i]) && $date >= $stoppedOn[$i] ? 'absent' : 'present';
        }

        Attendance::recordDay($this->section->id, $date, $statuses);
    }

    // Recorded afterwards. Two of them are known to be gone for good, so they
    // are withdrawn; the other two are treated as an open-ended break.
    SectionWithdrawalService::withdraw($registrations[3]->fresh(), $stoppedOn[3], 'انسحب');
    SectionWithdrawalService::withdraw($registrations[20]->fresh(), $stoppedOn[20], 'انسحب');
    SessionBillingService::pause($registrations[7]->fresh(), $stoppedOn[7], 'انقطع عن الحضور');
    SessionBillingService::pause($registrations[12]->fresh(), $stoppedOn[12], 'انقطع عن الحضور');

    foreach (range(1, 27) as $i) {
        $from = $joinedOn[$i] ?? '2026-06-01';
        $until = $stoppedOn[$i] ?? null;

        $shouldBe = count(array_filter(
            $dates,
            fn (string $d): bool => $d >= $from && ($until === null || $d < $until),
        ));

        expect($registrations[$i]->fresh()->sessions_counted)
            ->toBe($shouldBe, "عدد الحصص المحتسبة على طالب {$i}");
    }

    // A withdrawn student keeps every mark made while they were there, but
    // drops off the sheet from the day they left.
    $withdrawn = $registrations[3];

    expect(Attendance::query()->where('student_id', $withdrawn->student_id)->count())
        ->toBe(count($dates))
        ->and($this->section->rosterOn(end($dates))->pluck('student_id'))
        ->not->toContain($withdrawn->student_id)
        ->and($this->section->rosterOn('2026-06-20')->pluck('student_id'))
        ->toContain($withdrawn->student_id);

    // A student on an open-ended break stays on the sheet — the centre is
    // still expecting them back.
    expect($this->section->rosterOn(end($dates))->pluck('student_id'))
        ->toContain($registrations[7]->student_id);

    // Withdrawn students hand their seats back.
    expect(Registration::query()->where('section_id', $this->section->id)->stillEnrolled()->count())
        ->toBe(25);

    // Recalculating the whole section changes nothing.
    $before = collect($registrations)->map(fn (Registration $r): int => $r->fresh()->sessions_counted)->all();
    SessionBillingService::recountSection($this->section->id);

    expect(collect($registrations)->map(fn (Registration $r): int => $r->fresh()->sessions_counted)->all())
        ->toBe($before);
});

it('shows the roster of a past day as it stood on that day', function () {
    $early = Registration::create([
        'student_id' => Student::create([
            'name' => ['ar' => 'قديم', 'en' => 'Early'],
            'username' => 'roster_early_'.uniqid(),
            'password' => 'password',
            'status' => 'active',
        ])->id,
        'section_id' => $this->section->id,
        'enrolled_at' => '2026-06-01',
    ]);

    $late = Registration::create([
        'student_id' => Student::create([
            'name' => ['ar' => 'جديد', 'en' => 'Late'],
            'username' => 'roster_late_'.uniqid(),
            'password' => 'password',
            'status' => 'active',
        ])->id,
        'section_id' => $this->section->id,
        'enrolled_at' => '2026-08-05',
    ]);

    // A sheet for a June lesson lists one student; an August one lists both.
    expect($this->section->rosterOn('2026-06-10')->pluck('student_id')->all())
        ->toBe([$early->student_id])
        ->and($this->section->rosterOn('2026-08-10')->pluck('student_id')->sort()->values()->all())
        ->toBe(collect([$early->student_id, $late->student_id])->sort()->values()->all());
});
