<?php

use App\Filament\Admin\Pages\AttendanceRecords;
use App\Filament\Admin\Pages\SectionsCalendar;
use App\Filament\Admin\Resources\Registrations\Actions\CollectPaymentAction;
use App\Filament\Admin\Resources\Students\Pages\ViewStudent;
use App\Models\Attendance;
use App\Models\PaymentType;
use App\Models\Registration;
use App\Models\Room;
use App\Models\Section;
use App\Models\SectionTime;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Trainer;
use App\Models\User;
use App\Services\FinancialDueService;
use App\Support\PermissionCatalog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * One centre, run end to end, instead of a dozen isolated one-record cases.
 *
 * Three trainers, three rooms, six sections whose Saturday slots sit next to
 * each other rather than on top of each other, students taking two and three
 * courses at once, money arriving in parts, and a month of attendance behind
 * it. The individual rules have their own tests; this one is here to catch the
 * things that only go wrong once there is more than one of everything.
 *
 * The Saturday timetable it builds:
 *
 *   room 1   15:30–17:00  عربي/أ.سامي      17:00–18:30  إنجليزي/أ.ريم
 *   room 2   16:00–17:30  رياضيات/أ.هالة
 *   room 10  15:45–17:15  علوم/أ.نور
 *
 * Deliberately staggered: room 1's two lessons touch but never overlap, and
 * the three rooms all run through 16:00 with three different trainers.
 */
function centre(): array
{
    $admin = User::create([
        'name' => 'Centre Owner',
        'email' => 'centre-'.uniqid().'@ma.test',
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
        'is_active' => true,
    ]);

    foreach (PermissionCatalog::allGates() as $gate) {
        Permission::firstOrCreate(['name' => $gate, 'guard_name' => 'web']);
    }

    $role = Role::create(['name' => 'centre-'.uniqid(), 'guard_name' => 'web']);
    $role->syncPermissions(PermissionCatalog::allGates());
    $admin->assignRole($role);

    $rooms = [
        1 => Room::create(['number' => '1', 'capacity' => 30]),
        2 => Room::create(['number' => '2', 'capacity' => 20]),
        // Numbered 10 on purpose: it has to sort after 2, not after 1.
        10 => Room::create(['number' => '10', 'capacity' => 15]),
    ];

    $trainers = [
        'sami' => Trainer::create(['name' => ['ar' => 'أ. سامي', 'en' => 'Sami'], 'username' => 'c_sami_'.uniqid(), 'password' => 'password', 'default_rate' => 50]),
        'reem' => Trainer::create(['name' => ['ar' => 'أ. ريم', 'en' => 'Reem'], 'username' => 'c_reem_'.uniqid(), 'password' => 'password', 'default_rate' => 40]),
        'noor' => Trainer::create(['name' => ['ar' => 'أ. نور', 'en' => 'Noor'], 'username' => 'c_noor_'.uniqid(), 'password' => 'password', 'default_rate' => 60]),
        'hala' => Trainer::create(['name' => ['ar' => 'أ. هالة', 'en' => 'Hala'], 'username' => 'c_hala_'.uniqid(), 'password' => 'password', 'default_rate' => 45]),
    ];

    $subjects = [
        'arabic' => Subject::create(['name' => ['ar' => 'اللغة العربية', 'en' => 'Arabic']]),
        'english' => Subject::create(['name' => ['ar' => 'اللغة الإنجليزية', 'en' => 'English']]),
        'maths' => Subject::create(['name' => ['ar' => 'الرياضيات', 'en' => 'Maths']]),
        'science' => Subject::create(['name' => ['ar' => 'العلوم', 'en' => 'Science']]),
    ];

    $makeSection = function (string $name, Trainer $trainer, Subject $subject, array $attributes = []) {
        return Section::create(array_merge([
            'name' => $name,
            'subject_id' => $subject->id,
            'trainer_id' => $trainer->id,
            'price' => 300,
            // Started well before the month on screen, so the calendar's own
            // default period (which opens on the first of the month) shows the
            // timetable rather than a term that had not begun yet.
            'start_date' => now()->subMonths(2)->toDateString(),
            'end_date' => now()->addMonths(3)->toDateString(),
            'capacity' => 10,
            'min_capacity' => 3,
        ], $attributes));
    };

    $sections = [
        'arabic' => $makeSection('عربي أ', $trainers['sami'], $subjects['arabic'], ['price' => 300]),
        'english' => $makeSection('إنجليزي أ', $trainers['reem'], $subjects['english'], ['price' => 200]),
        // أ. هالة, not أ. ريم: Maths runs 16:00–17:30 and ريم already has the
        // 17:00 English lesson, so giving her both would be exactly the
        // double-booking the rest of this file is about.
        'maths' => $makeSection('رياضيات أ', $trainers['hala'], $subjects['maths'], ['price' => 250]),
        'science' => $makeSection('علوم أ', $trainers['noor'], $subjects['science'], ['price' => 150, 'capacity' => 2, 'min_capacity' => 2]),
    ];

    SectionTime::create(['section_id' => $sections['arabic']->id, 'room_id' => $rooms[1]->id, 'day' => 'saturday', 'start_time' => '15:30', 'end_time' => '17:00']);
    // Starts the minute the Arabic lesson ends, in the same room, with a
    // different trainer: adjacent, not overlapping.
    SectionTime::create(['section_id' => $sections['english']->id, 'room_id' => $rooms[1]->id, 'day' => 'saturday', 'start_time' => '17:00', 'end_time' => '18:30']);
    // Straddles both of room 1's lessons, but in room 2 with its own trainer.
    SectionTime::create(['section_id' => $sections['maths']->id, 'room_id' => $rooms[2]->id, 'day' => 'saturday', 'start_time' => '16:00', 'end_time' => '17:30']);
    SectionTime::create(['section_id' => $sections['science']->id, 'room_id' => $rooms[10]->id, 'day' => 'saturday', 'start_time' => '15:45', 'end_time' => '17:15']);

    return compact('admin', 'rooms', 'trainers', 'subjects', 'sections');
}

/** A student, enrolled nowhere yet. */
function centreStudent(string $name): Student
{
    return Student::create([
        'name' => ['ar' => $name, 'en' => $name],
        'username' => 'c_stu_'.uniqid(),
        'password' => 'password',
        'status' => 'active',
    ]);
}

beforeEach(function () {
    $this->centre = centre();
    $this->paymentType = PaymentType::create(['name' => 'نقداً '.uniqid()]);
    $this->actingAs($this->centre['admin']);
});

// ------------------------------------------------------------- the timetable

it('accepts a full Saturday of staggered lessons across three rooms', function () {
    // Everything the centre() helper built had to pass the observer to exist.
    expect(SectionTime::query()->where('day', 'saturday')->count())->toBe(4);
});

it('refuses a fifth lesson that overlaps room 1 by a single minute', function () {
    $intruder = Section::create([
        'name' => 'دخيل الغرفة',
        'subject_id' => $this->centre['subjects']['science']->id,
        'trainer_id' => $this->centre['trainers']['noor']->id,
        'price' => 0,
        'start_date' => now()->subWeek()->toDateString(),
        'end_date' => now()->addMonths(2)->toDateString(),
    ]);

    // 16:59 – 17:30: one minute inside the Arabic lesson, and one minute
    // outside the English one. A "nearly free" room is not a free room.
    expect(fn () => SectionTime::create([
        'section_id' => $intruder->id,
        'room_id' => $this->centre['rooms'][1]->id,
        'day' => 'saturday',
        'start_time' => '16:59',
        'end_time' => '17:30',
    ]))->toThrow(ValidationException::class);
});

it('lets a new lesson slot exactly into the gap between two others', function () {
    $filler = Section::create([
        'name' => 'حصة الفجوة',
        'subject_id' => $this->centre['subjects']['science']->id,
        'trainer_id' => $this->centre['trainers']['noor']->id,
        'price' => 0,
        'start_date' => now()->subWeek()->toDateString(),
        'end_date' => now()->addMonths(2)->toDateString(),
    ]);

    // Room 2 is busy 16:00–17:30 and free either side of it.
    SectionTime::create([
        'section_id' => $filler->id,
        'room_id' => $this->centre['rooms'][2]->id,
        'day' => 'saturday',
        'start_time' => '17:30',
        'end_time' => '19:00',
    ]);

    expect($filler->times()->count())->toBe(1);
});

it('refuses to put one trainer in two rooms at the same hour', function () {
    // أ. ريم teaches English in room 1 from 17:00. A second lesson of hers
    // running 16:30–17:45 in room 10 would need her in two places at once,
    // even though room 10 is free at that hour.
    $third = Section::create([
        'name' => 'شعبة ريم الثانية',
        'subject_id' => $this->centre['subjects']['english']->id,
        'trainer_id' => $this->centre['trainers']['reem']->id,
        'price' => 0,
        'start_date' => now()->subWeek()->toDateString(),
        'end_date' => now()->addMonths(2)->toDateString(),
    ]);

    expect(fn () => SectionTime::create([
        'section_id' => $third->id,
        'room_id' => $this->centre['rooms'][10]->id,
        'day' => 'saturday',
        'start_time' => '16:30',
        'end_time' => '17:45',
    ]))->toThrow(ValidationException::class);
});

it('reopens last term\'s slot once that course has ended', function () {
    $lastTerm = Section::create([
        'name' => 'دورة الفصل الماضي',
        'subject_id' => $this->centre['subjects']['arabic']->id,
        'trainer_id' => $this->centre['trainers']['sami']->id,
        'price' => 0,
        'start_date' => now()->subMonths(5)->toDateString(),
        'end_date' => now()->subMonths(2)->toDateString(),
    ]);

    SectionTime::create([
        'section_id' => $lastTerm->id,
        'room_id' => $this->centre['rooms'][2]->id,
        'day' => 'wednesday',
        'start_time' => '15:30',
        'end_time' => '17:00',
    ]);

    $thisTerm = Section::create([
        'name' => 'دورة هذا الفصل',
        'subject_id' => $this->centre['subjects']['arabic']->id,
        'trainer_id' => $this->centre['trainers']['sami']->id,
        'price' => 0,
        'start_date' => now()->toDateString(),
        'end_date' => now()->addMonths(3)->toDateString(),
    ]);

    // Same trainer, same room, same hour — but the old course is over.
    SectionTime::create([
        'section_id' => $thisTerm->id,
        'room_id' => $this->centre['rooms'][2]->id,
        'day' => 'wednesday',
        'start_time' => '15:30',
        'end_time' => '17:00',
    ]);

    expect($thisTerm->times()->count())->toBe(1);
});

// ------------------------------------------------------------- the calendar

it('reads the Saturday timetable earliest first, then room 1 to room 10', function () {
    $page = Livewire::actingAs($this->centre['admin'])->test(SectionsCalendar::class);
    $saturday = Carbon::parse($page->instance()->periodStart());

    while ($saturday->dayOfWeek !== Carbon::SATURDAY) {
        $saturday->addDay();
    }

    $order = $page->instance()->eventsFor($saturday)->map(fn (SectionTime $t): string => sprintf(
        '%s %s',
        Carbon::parse($t->start_time)->format('H:i'),
        $t->room?->number,
    ))->all();

    expect($order)->toBe([
        '15:30 1',   // earliest
        '15:45 10',  // …then by time, whatever the room number
        '16:00 2',
        '17:00 1',
    ]);
});

it('narrows the calendar to one room at a time', function () {
    foreach ([1 => 1, 2 => 1, 10 => 1] as $roomKey => $expected) {
        $page = Livewire::actingAs($this->centre['admin'])
            ->test(SectionsCalendar::class)
            ->fillForm(['room_id' => $this->centre['rooms'][$roomKey]->id]);

        $saturday = Carbon::parse($page->instance()->periodStart());
        while ($saturday->dayOfWeek !== Carbon::SATURDAY) {
            $saturday->addDay();
        }

        $count = $page->instance()->eventsFor($saturday)->count();

        // Room 1 holds two lessons; the other two hold one each.
        expect($count)->toBe($roomKey === 1 ? 2 : $expected);
    }
});

it('prints the week with every room in walking order', function () {
    $page = Livewire::actingAs($this->centre['admin'])
        ->test(SectionsCalendar::class)
        ->call('setSpan', SectionsCalendar::VIEW_WEEK);

    $weeks = $page->instance()->weeklyGrid();

    expect($weeks)->toHaveCount(1)
        ->and(array_column($weeks[0]['rooms'], 'label'))
        ->toBe([__('Room').' 1', __('Room').' 2', __('Room').' 10']);

    $response = $page->instance()->exportWeeklyPdf();

    ob_start();
    $response->sendContent();
    $body = ob_get_clean();

    expect($body)->toStartWith('%PDF')
        ->and($body)->toContain('/MediaBox [0 0 841.890 595.280]');
});

// ----------------------------------------------------------------- enrolment

it('puts one student through three courses without a clash', function () {
    $student = centreStudent('طالب الثلاث مواد');

    // Arabic 15:30–17:00 room 1, Maths 16:00–17:30 room 2 — these overlap, so
    // only one of them can be his. English 17:00–18:30 does not.
    Registration::create(['student_id' => $student->id, 'section_id' => $this->centre['sections']['arabic']->id, 'amount_due' => 300, 'amount_paid' => 300]);
    Registration::create(['student_id' => $student->id, 'section_id' => $this->centre['sections']['english']->id, 'amount_due' => 200, 'amount_paid' => 200]);

    // The third is Science at 15:45, straight through his Arabic lesson.
    Livewire::test(ViewStudent::class, ['record' => $student->getKey()])
        ->callAction('enrollInSection', [
            'section_id' => $this->centre['sections']['science']->id,
            'enrolled_at' => now()->toDateString(),
            'amount_due' => 150,
            'exemption_amount' => 0,
            'amount_paid' => 150,
        ])
        ->assertHasActionErrors(['section_id']);

    expect(Registration::where('student_id', $student->id)->count())->toBe(2);
});

it('fills a small section and then turns the next student away', function () {
    // Science holds two seats.
    $first = centreStudent('أول المسجلين');
    $second = centreStudent('ثاني المسجلين');
    $third = centreStudent('ثالث المسجلين');

    foreach ([$first, $second] as $student) {
        Livewire::test(ViewStudent::class, ['record' => $student->getKey()])
            ->callAction('enrollInSection', [
                'section_id' => $this->centre['sections']['science']->id,
                'enrolled_at' => now()->toDateString(),
                'amount_due' => 150,
                'exemption_amount' => 0,
                'amount_paid' => 150,
            ])
            ->assertHasNoActionErrors();
    }

    $science = $this->centre['sections']['science']->fresh();

    expect($science->enrolledCount())->toBe(2)
        ->and($science->isFull())->toBeTrue()
        ->and($science->seatsSummary())->toBe('2 / 2');

    Livewire::test(ViewStudent::class, ['record' => $third->getKey()])
        ->callAction('enrollInSection', [
            'section_id' => $science->id,
            'enrolled_at' => now()->toDateString(),
            'amount_due' => 150,
            'exemption_amount' => 0,
            'amount_paid' => 150,
        ])
        ->assertHasActionErrors(['section_id']);

    expect($science->fresh()->enrolledCount())->toBe(2);
});

it('frees the seat again when someone withdraws', function () {
    $leaver = centreStudent('المنسحب');
    $waiting = centreStudent('المنتظر');

    foreach ([$leaver, centreStudent('الثاني')] as $student) {
        Registration::create([
            'student_id' => $student->id,
            'section_id' => $this->centre['sections']['science']->id,
            'amount_due' => 150,
            'amount_paid' => 150,
        ]);
    }

    expect($this->centre['sections']['science']->fresh()->isFull())->toBeTrue();

    Registration::where('student_id', $leaver->id)->first()->update(['left_at' => now()->toDateString()]);

    Livewire::test(ViewStudent::class, ['record' => $waiting->getKey()])
        ->callAction('enrollInSection', [
            'section_id' => $this->centre['sections']['science']->id,
            'enrolled_at' => now()->toDateString(),
            'amount_due' => 150,
            'exemption_amount' => 0,
            'amount_paid' => 150,
        ])
        ->assertHasNoActionErrors();

    expect($this->centre['sections']['science']->fresh()->enrolledCount())->toBe(2);
});

it('warns while a section is short of the students it needs', function () {
    $arabic = $this->centre['sections']['arabic'];

    Registration::create(['student_id' => centreStudent('واحد')->id, 'section_id' => $arabic->id, 'amount_due' => 300, 'amount_paid' => 300]);

    expect($arabic->fresh()->isBelowMinimum())->toBeTrue();

    Registration::create(['student_id' => centreStudent('اثنان')->id, 'section_id' => $arabic->id, 'amount_due' => 300, 'amount_paid' => 300]);
    Registration::create(['student_id' => centreStudent('ثلاثة')->id, 'section_id' => $arabic->id, 'amount_due' => 300, 'amount_paid' => 300]);

    expect($arabic->fresh()->isBelowMinimum())->toBeFalse()
        ->and($arabic->fresh()->seatsSummary())->toBe('3 / 10');
});

// -------------------------------------------------------------------- money

it('collects three courses separately without the money leaking between them', function () {
    $student = centreStudent('طالب الدفعات');

    $arabic = Registration::create(['student_id' => $student->id, 'section_id' => $this->centre['sections']['arabic']->id, 'amount_due' => 300, 'amount_paid' => 300]);
    $english = Registration::create(['student_id' => $student->id, 'section_id' => $this->centre['sections']['english']->id, 'amount_due' => 200, 'amount_paid' => 200]);
    $science = Registration::create(['student_id' => $student->id, 'section_id' => $this->centre['sections']['science']->id, 'amount_due' => 150, 'amount_paid' => 150]);

    // Pays English in full, half of Science, nothing towards Arabic — and
    // Arabic is the oldest bill, the one a plain deposit would have settled.
    CollectPaymentAction::collect($english, ['amount' => 200, 'payment_type_id' => $this->paymentType->id]);
    CollectPaymentAction::collect($science, ['amount' => 75, 'payment_type_id' => $this->paymentType->id]);

    expect($english->fresh()->financial_status)->toBe('ok')
        ->and(FinancialDueService::remainingBalance($english->fresh()))->toBe(0.0)
        ->and((float) $science->fresh()->funded_amount)->toBe(75.0)
        ->and(FinancialDueService::remainingBalance($science->fresh()))->toBe(75.0)
        ->and($science->fresh()->financial_status)->toBe('due')
        // Untouched, as instructed.
        ->and((float) $arabic->fresh()->funded_amount)->toBe(0.0)
        ->and($arabic->fresh()->financial_status)->toBe('overdue')
        // 650 charged, 275 collected.
        ->and(round($student->fresh()->balanceFloat, 2))->toBe(-375.0);
});

it('credits each trainer only their own share of what was collected', function () {
    $student = centreStudent('طالب الحصص');

    $arabic = Registration::create(['student_id' => $student->id, 'section_id' => $this->centre['sections']['arabic']->id, 'amount_due' => 300, 'amount_paid' => 300]);
    $science = Registration::create(['student_id' => $student->id, 'section_id' => $this->centre['sections']['science']->id, 'amount_due' => 150, 'amount_paid' => 150]);

    CollectPaymentAction::collect($arabic, ['amount' => 300, 'payment_type_id' => $this->paymentType->id]);
    CollectPaymentAction::collect($science, ['amount' => 50, 'payment_type_id' => $this->paymentType->id]);

    // أ. سامي takes 50% of 300; أ. نور takes 60% of the 50 collected so far.
    expect((float) $arabic->fresh()->trainer_credited_amount)->toBe(150.0)
        ->and((float) $science->fresh()->trainer_credited_amount)->toBe(30.0)
        ->and(round($this->centre['trainers']['sami']->fresh()->balanceFloat, 2))->toBe(150.0)
        ->and(round($this->centre['trainers']['noor']->fresh()->balanceFloat, 2))->toBe(30.0)
        // أ. ريم has been paid nothing: none of her students paid.
        ->and(round($this->centre['trainers']['reem']->fresh()->balanceFloat, 2))->toBe(0.0);
});

it('leaves an overpayment as credit rather than spending it on another course', function () {
    $student = centreStudent('طالب الزيادة');

    $arabic = Registration::create(['student_id' => $student->id, 'section_id' => $this->centre['sections']['arabic']->id, 'amount_due' => 300, 'amount_paid' => 300]);
    $english = Registration::create(['student_id' => $student->id, 'section_id' => $this->centre['sections']['english']->id, 'amount_due' => 200, 'amount_paid' => 200]);

    CollectPaymentAction::collect($english, ['amount' => 500, 'payment_type_id' => $this->paymentType->id]);

    expect((float) $english->fresh()->funded_amount)->toBe(200.0)
        ->and((float) $arabic->fresh()->funded_amount)->toBe(0.0)
        // 500 in, 500 charged: the extra 300 is sitting on the wallet, not on
        // the Arabic bill.
        ->and(round($student->fresh()->balanceFloat, 2))->toBe(0.0)
        ->and($arabic->fresh()->financial_status)->toBe('overdue');
});

// --------------------------------------------------------------- attendance

it('builds a month of attendance for a whole class and prints it', function () {
    $arabic = $this->centre['sections']['arabic'];

    $roster = collect(['سالم', 'ليان', 'يوسف', 'مريم', 'خالد'])
        ->map(function (string $name) use ($arabic): Student {
            $student = centreStudent($name);

            Registration::create([
                'student_id' => $student->id,
                'section_id' => $arabic->id,
                'amount_due' => 300,
                'amount_paid' => 300,
            ]);

            return $student;
        });

    // Six Saturdays, with a believable spread rather than everyone present.
    $statuses = ['present', 'present', 'late', 'absent', 'excused', 'present'];

    foreach (range(0, 5) as $week) {
        $date = now()->subWeeks(5 - $week)->toDateString();

        foreach ($roster as $index => $student) {
            Attendance::create([
                'section_id' => $arabic->id,
                'student_id' => $student->id,
                'date' => $date,
                'status' => $statuses[($index + $week) % 6],
            ]);
        }
    }

    $page = Livewire::actingAs($this->centre['admin'])
        ->test(AttendanceRecords::class)
        ->set('sheetSectionId', $arabic->id);

    $sheet = $page->instance()->sheet;

    expect($sheet['dates'])->toHaveCount(6)
        ->and($sheet['rows'])->toHaveCount(5);

    // Every student's marks add up to the six lessons held.
    foreach ($sheet['rows'] as $row) {
        expect(array_sum($row['counts']))->toBe(6);
    }

    $response = $page->instance()->exportSheetPdf();

    ob_start();
    $response->sendContent();
    $body = ob_get_clean();

    expect($body)->toStartWith('%PDF')
        ->and($body)->toContain('/MediaBox [0 0 841.890 595.280]');
});

it('shows each student what they still owe on the attendance sheet', function () {
    $arabic = $this->centre['sections']['arabic'];

    $paid = centreStudent('دفع كاملاً');
    $partial = centreStudent('دفع جزءاً');

    $paidRegistration = Registration::create(['student_id' => $paid->id, 'section_id' => $arabic->id, 'amount_due' => 300, 'amount_paid' => 300]);
    $partialRegistration = Registration::create(['student_id' => $partial->id, 'section_id' => $arabic->id, 'amount_due' => 300, 'amount_paid' => 300]);

    CollectPaymentAction::collect($paidRegistration, ['amount' => 300, 'payment_type_id' => $this->paymentType->id]);
    CollectPaymentAction::collect($partialRegistration, ['amount' => 180, 'payment_type_id' => $this->paymentType->id]);

    foreach ([$paid, $partial] as $student) {
        Attendance::create([
            'section_id' => $arabic->id,
            'student_id' => $student->id,
            'date' => now()->subWeek()->toDateString(),
            'status' => 'present',
        ]);
    }

    $rows = collect(
        Livewire::actingAs($this->centre['admin'])
            ->test(AttendanceRecords::class)
            ->set('sheetSectionId', $arabic->id)
            ->instance()
            ->sheet['rows']
    )->keyBy(fn (array $row): int => $row['student']->id);

    $settled = AttendanceRecords::financialAmounts($rows[$paid->id]);
    $owing = AttendanceRecords::financialAmounts($rows[$partial->id]);

    expect($settled)->toContain('300')
        // Nothing left to owe, so no "remaining" half.
        ->and($settled)->not->toContain(__('Remaining'))
        ->and($owing)->toContain('180')
        ->and($owing)->toContain(__('Remaining'))
        ->and($owing)->toContain('120');
});
