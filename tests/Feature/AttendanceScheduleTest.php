<?php

use App\Filament\Admin\Pages\AttendanceRecords;
use App\Filament\Admin\Pages\TakeAttendance;
use App\Livewire\ScheduleCalendar;
use App\Models\Attendance;
use App\Models\Registration;
use App\Models\Section;
use App\Models\SectionSession;
use App\Models\SectionTime;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/** A user holding just the two gates the attendance page checks. */
function attendanceAdmin(): User
{
    $user = User::create([
        'name' => 'Attendance Tester',
        'email' => 'att-'.uniqid().'@ma.test',
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
        'is_active' => true,
    ]);

    $gates = ['attendance.index', 'attendance.update'];

    foreach ($gates as $gate) {
        Permission::firstOrCreate(['name' => $gate, 'guard_name' => 'web']);
    }

    $role = Role::create(['name' => 'att-role-'.uniqid(), 'guard_name' => 'web']);
    $role->syncPermissions($gates);
    $user->assignRole($role);

    return $user;
}

/** A section that meets on the given weekdays, with one enrolled student. */
function scheduledSection(array $weekdays, array $attributes = []): Section
{
    $section = Section::create(array_merge([
        'name' => 'Scheduled '.uniqid(),
        'subject_id' => Subject::create(['name' => 'Physics '.uniqid(), 'course_type_id' => null])->id,
        'start_date' => now()->subMonths(2),
        'end_date' => now()->addMonths(2),
        'price' => 0,
    ], $attributes));

    foreach ($weekdays as $day) {
        SectionTime::create([
            'section_id' => $section->id,
            'day' => $day,
            'start_time' => '16:00',
            'end_time' => '17:30',
        ]);
    }

    $student = Student::create([
        'name' => 'Scheduled Student',
        'username' => 'sched_'.uniqid(),
        'password' => Hash::make('password'),
        'status' => 'active',
        'is_active' => true,
        'student_number' => 'STU-'.strtoupper(substr(uniqid(), -5)),
    ]);

    Registration::create([
        'student_id' => $student->id,
        'section_id' => $section->id,
        // Backdated on purpose. Without it the registration starts today, and
        // `recordDay()` skips past dates because the student had not joined —
        // which would make the timetable tests below pass for the wrong reason.
        'enrolled_at' => now()->subMonths(2),
        'amount_due' => 0,
        'amount_paid' => 0,
    ]);

    return $section->fresh(['times']);
}

test('a section only meets on its timetable weekdays inside its date range', function () {
    $section = scheduledSection(['sunday', 'thursday']);

    $sunday = now()->next('sunday');
    $monday = now()->next('monday');

    expect($section->meetsOn($sunday))->toBeTrue()
        ->and($section->meetsOn($monday))->toBeFalse()
        // Correct weekday, but the course had not started yet.
        ->and($section->meetsOn($sunday->copy()->subMonths(6)))->toBeFalse();
});

test('a section with no timetable rows accepts any day', function () {
    $section = scheduledSection([]);

    expect($section->meetsOn(now()))->toBeTrue()
        ->and($section->meetsOn(now()->addDay()))->toBeTrue();
});

test('take attendance refuses to save a day the section does not meet on', function () {
    $section = scheduledSection(['sunday']);
    $student = $section->registrations()->first()->student_id;
    $this->actingAs(attendanceAdmin());

    $monday = now()->subWeek()->next('monday')->toDateString();

    Livewire::test(TakeAttendance::class)
        ->set('sectionId', $section->id)
        ->set('date', $monday)
        ->set('statuses', [$student => 'present'])
        ->call('save');

    expect(Attendance::where('section_id', $section->id)->count())->toBe(0);
});

/**
 * Attendance entered before the timetable existed — or before it was changed —
 * sits on a day the section no longer meets. Locking that day would leave a
 * wrong record on the books with no way to correct it.
 */
test('an already-recorded day stays editable even when it is off the timetable', function () {
    $section = scheduledSection(['sunday']);
    $student = $section->registrations()->first()->student_id;
    $this->actingAs(attendanceAdmin());

    $monday = now()->subWeek()->next('monday')->toDateString();

    // A legacy row, written straight to the table the way older data would be.
    Attendance::create([
        'section_id' => $section->id,
        'student_id' => $student,
        'date' => $monday,
        'status' => 'absent',
    ]);

    Livewire::test(TakeAttendance::class)
        ->set('sectionId', $section->id)
        ->call('selectDate', $monday)
        // The picker opens it…
        ->assertSet('date', $monday)
        ->set('statuses', [$student => 'present'])
        ->call('save');

    // …and the correction sticks.
    expect(Attendance::where('section_id', $section->id)->where('student_id', $student)->first()->status)
        ->toBe('present');
});

test('take attendance saves a day that is on the timetable', function () {
    $section = scheduledSection(['sunday']);
    $student = $section->registrations()->first()->student_id;
    $this->actingAs(attendanceAdmin());

    $sunday = now()->subWeek()->next('sunday')->toDateString();

    Livewire::test(TakeAttendance::class)
        ->set('sectionId', $section->id)
        ->set('date', $sunday)
        ->set('statuses', [$student => 'present'])
        ->call('save');

    expect(Attendance::where('section_id', $section->id)->count())->toBe(1);
});

test('the day picker marks days that already have attendance and blocks off-timetable days', function () {
    $section = scheduledSection(['sunday']);
    $student = $section->registrations()->first()->student_id;

    $sunday = now()->startOfMonth()->next('sunday');

    Attendance::create([
        'section_id' => $section->id,
        'student_id' => $student,
        'date' => $sunday->toDateString(),
        'status' => 'present',
    ]);

    $page = new TakeAttendance;
    $page->sectionId = $section->id;
    $page->date = $sunday->toDateString();
    $page->calendarMonth = $sunday->copy()->startOfMonth()->toDateString();

    $days = collect($page->calendar()['days'])->keyBy('date');

    expect($days[$sunday->toDateString()]['isRecorded'])->toBeTrue()
        ->and($days[$sunday->toDateString()]['isSessionDay'])->toBeTrue()
        ->and($days[$sunday->toDateString()]['present'])->toBe(1)
        ->and($days[$sunday->copy()->addDay()->toDateString()]['isSessionDay'])->toBeFalse()
        ->and($days[$sunday->copy()->addDay()->toDateString()]['isRecorded'])->toBeFalse();
});

test('the attendance sheet exports as a PDF carrying the same grid', function () {
    $section = scheduledSection(['sunday']);
    $student = $section->registrations()->first()->student_id;

    foreach ([7, 14] as $daysAgo) {
        Attendance::create([
            'section_id' => $section->id,
            'student_id' => $student,
            'date' => now()->subDays($daysAgo)->toDateString(),
            'status' => 'present',
        ]);
    }

    $this->actingAs(attendanceAdmin());

    Livewire::test(AttendanceRecords::class)
        ->set('sheetSectionId', $section->id)
        ->call('exportSheetPdf')
        ->assertFileDownloaded();
});

test('the attendance sheet pages by the section own sessions per cycle', function () {
    $section = scheduledSection([], [
        'fee_type' => Section::FEE_TYPE_PER_SESSIONS,
        'sessions_per_cycle' => 8,
        'cycle_fee' => 100,
        'start_date' => now()->subMonths(3),
    ]);

    $student = $section->registrations()->first()->student_id;

    // 10 lessons: 8 fill the first "month", 2 spill into the second.
    foreach (range(1, 10) as $i) {
        Attendance::create([
            'section_id' => $section->id,
            'student_id' => $student,
            'date' => now()->subDays(40 - $i)->toDateString(),
            'status' => 'present',
        ]);
    }

    $page = new AttendanceRecords;
    $page->sheetSectionId = $section->id;

    $first = $page->sheet();

    expect($first['perMonth'])->toBe(8)
        ->and($first['months'])->toBe(2)
        ->and($first['dates'])->toHaveCount(8);

    $page->goToMonth(2);

    expect($page->sheet()['dates'])->toHaveCount(2);
});

/**
 * Extra lessons — the ones someone enters by hand for a single date — used to
 * be invisible twice over: off the calendar, because every grid is drawn from
 * the weekly timetable, and locked out of the attendance sheet, because the
 * timetable was also what decided which days could be opened. Billing counted
 * them all the same, so a centre could be charging for a lesson it had no way
 * to mark anybody present at.
 */
test('an extra lesson opens the attendance sheet on an off-timetable day', function () {
    $section = scheduledSection(['sunday']);
    $student = $section->registrations()->first()->student_id;
    $this->actingAs(attendanceAdmin());

    $monday = now()->subWeek()->next('monday');

    SectionSession::create([
        'section_id' => $section->id,
        'date' => $monday->toDateString(),
        'type' => SectionSession::TYPE_REGULAR,
        'status' => SectionSession::STATUS_HELD,
    ]);

    Livewire::test(TakeAttendance::class)
        ->set('sectionId', $section->id)
        ->call('selectDate', $monday->toDateString())
        ->assertSet('date', $monday->toDateString())
        ->set('statuses', [$student => 'present'])
        ->call('save');

    expect(Attendance::where('section_id', $section->id)->whereDate('date', $monday)->count())->toBe(1);
});

test('a cancelled lesson does not open a day the timetable already refuses', function () {
    $section = scheduledSection(['sunday']);
    $monday = now()->subWeek()->next('monday');

    SectionSession::create([
        'section_id' => $section->id,
        'date' => $monday->toDateString(),
        'type' => SectionSession::TYPE_REGULAR,
        'status' => SectionSession::STATUS_CANCELLED,
        'cancellation_reason' => 'Holiday',
    ]);

    expect($section->fresh(['times'])->meetsOn($monday))->toBeFalse();
});

/**
 * A private lesson is one student's own paid hour. Opening the sheet on it
 * would have `resolveForDay()` raise a regular lesson beside it and charge the
 * whole roster for an afternoon they never sat.
 */
test('a private lesson does not open the attendance sheet', function () {
    $section = scheduledSection(['sunday']);
    $monday = now()->subWeek()->next('monday');

    SectionSession::create([
        'section_id' => $section->id,
        'date' => $monday->toDateString(),
        'type' => SectionSession::TYPE_PRIVATE,
        'status' => SectionSession::STATUS_HELD,
        'fee' => 80,
        'counts_toward_billing' => false,
    ]);

    expect($section->fresh(['times'])->meetsOn($monday))->toBeFalse();
});

/**
 * Taking attendance on a make-up lesson's day must reuse that lesson. Creating
 * a regular one beside it would put two lessons on one afternoon and charge the
 * per-session roster twice for it.
 */
test('taking attendance on a makeup lesson day does not create a second lesson', function () {
    $section = scheduledSection(['sunday']);
    $student = $section->registrations()->first()->student_id;
    $this->actingAs(attendanceAdmin());

    $monday = now()->subWeek()->next('monday');

    $makeup = SectionSession::create([
        'section_id' => $section->id,
        'date' => $monday->toDateString(),
        'type' => SectionSession::TYPE_MAKEUP,
        'status' => SectionSession::STATUS_HELD,
    ]);

    Livewire::test(TakeAttendance::class)
        ->set('sectionId', $section->id)
        ->call('selectDate', $monday->toDateString())
        ->set('statuses', [$student => 'present'])
        ->call('save');

    expect(SectionSession::where('section_id', $section->id)->whereDate('date', $monday)->count())->toBe(1)
        ->and(Attendance::where('section_id', $section->id)->whereDate('date', $monday)->first()->section_session_id)
        ->toBe($makeup->id);
});

test('the day picker opens the day an extra lesson was entered for', function () {
    $section = scheduledSection(['sunday']);

    $extraDay = now()->startOfMonth()->next('monday');

    SectionSession::create([
        'section_id' => $section->id,
        'date' => $extraDay->toDateString(),
        'type' => SectionSession::TYPE_REGULAR,
        'status' => SectionSession::STATUS_HELD,
    ]);

    $page = new TakeAttendance;
    $page->sectionId = $section->id;
    $page->date = $extraDay->toDateString();
    $page->calendarMonth = $extraDay->copy()->startOfMonth()->toDateString();

    $days = collect($page->calendar()['days'])->keyBy('date');

    expect($days[$extraDay->toDateString()]['isSessionDay'])->toBeTrue()
        // The Monday a week later has no lesson of its own and stays shut.
        ->and($days[$extraDay->copy()->addWeek()->toDateString()]['isSessionDay'])->toBeFalse();
});

test('the schedule calendar shows extra lessons alongside the weekly ones', function () {
    $section = scheduledSection(['sunday']);

    $extraDay = now()->startOfMonth()->next('tuesday');

    SectionSession::create([
        'section_id' => $section->id,
        'date' => $extraDay->toDateString(),
        'type' => SectionSession::TYPE_MAKEUP,
        'status' => SectionSession::STATUS_HELD,
        'start_time' => '18:00',
        'end_time' => '19:30',
    ]);

    $grid = Livewire::test(ScheduleCalendar::class, ['sectionIds' => [$section->id]])
        ->instance()
        ->grid();

    $days = collect($grid['days'])->keyBy(fn (array $day): string => $day['date']->toDateString());
    $lessons = $days[$extraDay->toDateString()]['lessons'];

    expect($lessons)->toHaveCount(1)
        ->and($lessons->first()->extra_session_type)->toBe(SectionSession::TYPE_MAKEUP)
        ->and(substr((string) $lessons->first()->start_time, 0, 5))->toBe('18:00');
});

test('the schedule calendar does not double up a lesson the timetable already draws', function () {
    $section = scheduledSection(['sunday']);

    $sunday = now()->startOfMonth()->next('sunday');

    // What taking attendance on a normal lesson day leaves behind.
    SectionSession::create([
        'section_id' => $section->id,
        'date' => $sunday->toDateString(),
        'type' => SectionSession::TYPE_REGULAR,
        'status' => SectionSession::STATUS_HELD,
    ]);

    $grid = Livewire::test(ScheduleCalendar::class, ['sectionIds' => [$section->id]])
        ->instance()
        ->grid();

    $days = collect($grid['days'])->keyBy(fn (array $day): string => $day['date']->toDateString());

    expect($days[$sunday->toDateString()]['lessons'])->toHaveCount(1);
});

/**
 * A section with no timetable rows meets on any day as far as the attendance
 * sheet is concerned, but a calendar drawn from the timetable has nothing to
 * draw for it — so its lessons only ever appear if they come from the rows.
 */
test('the schedule calendar shows lessons of a section that has no timetable', function () {
    $section = scheduledSection([]);

    $day = now()->startOfMonth()->addDays(3);

    SectionSession::create([
        'section_id' => $section->id,
        'date' => $day->toDateString(),
        'type' => SectionSession::TYPE_REGULAR,
        'status' => SectionSession::STATUS_HELD,
        'start_time' => '17:00',
        'end_time' => '18:00',
    ]);

    $grid = Livewire::test(ScheduleCalendar::class, ['sectionIds' => [$section->id]])
        ->instance()
        ->grid();

    $days = collect($grid['days'])->keyBy(fn (array $entry): string => $entry['date']->toDateString());

    expect($days[$day->toDateString()]['lessons'])->toHaveCount(1)
        ->and($days[$day->copy()->addDay()->toDateString()]['lessons'])->toHaveCount(0);
});
