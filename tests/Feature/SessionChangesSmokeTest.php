<?php

use App\Filament\Admin\Pages\AttendanceRecords;
use App\Filament\Admin\Pages\QuickEnroll;
use App\Filament\Admin\Pages\TakeAttendance;
use App\Filament\Admin\Resources\Sections\Pages\CreateSection;
use App\Filament\Admin\Resources\Sections\Pages\EditSection;
use App\Filament\Admin\Resources\Sections\Pages\ListSections;
use App\Filament\Admin\Resources\Students\Pages\ViewStudent;
use App\Filament\Admin\Resources\Trainers\Pages\CreateTrainer;
use App\Filament\Admin\Resources\Trainers\Pages\EditTrainer;
use App\Filament\Admin\Resources\Trainers\Pages\ViewTrainer;
use App\Models\Attendance;
use App\Models\Registration;
use App\Models\Section;
use App\Models\SectionTime;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Trainer;
use App\Models\User;
use App\Support\PermissionCatalog;
use App\Support\TrainerRate;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * One pass over everything this round of work touched, driven through the real
 * Filament pages rather than the services underneath — the forms are where a
 * renamed field or a dropped import actually breaks.
 */
function smokeAdmin(): User
{
    if (! User::query()->whereKey(1)->exists()) {
        User::create([
            'name' => 'Super Admin',
            'email' => 'smoke-super@ma.test',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
    }

    $user = User::create([
        'name' => 'Smoke Tester',
        'email' => 'smoke-'.uniqid().'@ma.test',
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
        'is_active' => true,
    ]);

    $gates = PermissionCatalog::allGates();

    foreach ($gates as $gate) {
        Permission::firstOrCreate(['name' => $gate, 'guard_name' => 'web']);
    }

    $role = Role::create(['name' => 'smoke-'.uniqid(), 'guard_name' => 'web']);
    $role->syncPermissions($gates);
    $user->assignRole($role);

    return $user;
}

beforeEach(function () {
    $this->actingAs(smokeAdmin());
});

/**
 * One trainer and one course, reused by every section a test builds. Held in a
 * plain static rather than `$this->` properties so editors do not flag each use
 * as an undefined property; the static is reset per test because the database
 * is, and these are looked up fresh by id.
 *
 * @return array{Trainer, Subject}
 */
function smokeFixtures(): array
{
    static $cached = null;

    // A new database means the cached rows are gone; rebuild them.
    if ($cached !== null && Trainer::query()->whereKey($cached[0]->id)->exists()) {
        return $cached;
    }

    $trainer = Trainer::create([
        'name' => ['ar' => 'أستاذ', 'en' => 'Trainer'],
        'username' => 'smoke_t_'.uniqid(),
        'password' => 'password',
        'default_rate' => 40,
    ]);

    $subject = Subject::create(['name' => ['ar' => 'مادة', 'en' => 'Subject']]);

    // The section form only offers trainers who teach the chosen course.
    $trainer->subjects()->attach($subject->id);

    return $cached = [$trainer, $subject];
}

/** A Sunday/Tuesday section with one enrolled student. */
function smokeSection(array $overrides = []): Section
{
    [$trainer, $subject] = smokeFixtures();

    $section = Section::create(array_merge([
        'name' => 'شعبة الاختبار',
        'subject_id' => $subject->id,
        'trainer_id' => $trainer->id,
        'price' => 0,
        'start_date' => '2026-08-01',
        'end_date' => '2026-12-31',
    ], $overrides));

    // Each section gets its own hour: the section-time observer rightly refuses
    // to put one trainer in two rooms at once, and several of these tests build
    // more than one section for the same trainer.
    static $slot = 8;
    $hour = str_pad((string) $slot++, 2, '0', STR_PAD_LEFT);

    foreach (['sunday', 'tuesday'] as $day) {
        SectionTime::create([
            'section_id' => $section->id,
            'day' => $day,
            'start_time' => $hour.':00',
            'end_time' => $hour.':45',
        ]);
    }

    $student = Student::create([
        'name' => ['ar' => 'طالب', 'en' => 'Student'],
        'username' => 'smoke_s_'.uniqid(),
        'password' => 'password',
        'status' => 'active',
        'student_number' => 'STU-'.strtoupper(substr(uniqid(), -5)),
    ]);

    Registration::create([
        'student_id' => $student->id,
        'section_id' => $section->id,
        'enrolled_at' => '2026-08-01',
        'amount_due' => 0,
        'amount_paid' => 0,
    ]);

    return $section->fresh(['times']);
}

it('renders the take-attendance page with its day picker', function () {
    $section = smokeSection();

    Livewire::test(TakeAttendance::class)
        ->assertSuccessful()
        ->set('sectionId', $section->id)
        ->call('selectDate', '2026-09-01') // a Tuesday
        ->assertSuccessful()
        ->assertSet('date', '2026-09-01');
});

it('refuses a day picked outside the timetable and keeps the old selection', function () {
    $section = smokeSection();

    Livewire::test(TakeAttendance::class)
        ->set('sectionId', $section->id)
        ->call('selectDate', '2026-09-01')
        ->call('selectDate', '2026-09-02') // Wednesday — not on the timetable
        ->assertSet('date', '2026-09-01');
});

it('moves the day picker between months', function () {
    smokeSection();

    Livewire::test(TakeAttendance::class)
        ->set('calendarMonth', '2026-09-01')
        ->call('shiftMonth', 1)
        ->assertSet('calendarMonth', '2026-10-01')
        ->call('shiftMonth', -2)
        ->assertSet('calendarMonth', '2026-08-01')
        ->assertSuccessful();
});

it('renders the attendance sheet and exports the month on screen as PDF', function () {
    $section = smokeSection([
        'fee_type' => Section::FEE_TYPE_PER_SESSIONS,
        'sessions_per_cycle' => 4,
        'cycle_fee' => 100,
        'auto_charge_cycles' => false,
    ]);

    $studentId = $section->registrations()->first()->student_id;

    // Six lessons at four per cycle → two pages.
    foreach (['2026-08-02', '2026-08-04', '2026-08-09', '2026-08-11', '2026-08-16', '2026-08-18'] as $date) {
        Attendance::recordDay($section->id, $date, [$studentId => 'present']);
    }

    $page = Livewire::test(AttendanceRecords::class)
        ->set('sheetSectionId', $section->id)
        ->assertSuccessful();

    expect($page->get('sheet')['perMonth'])->toBe(4)
        ->and($page->get('sheet')['months'])->toBe(2)
        ->and($page->get('sheet')['dates'])->toHaveCount(4);

    $page->call('exportSheetPdf')->assertFileDownloaded();

    // The second page carries only its own two lessons.
    $page->call('goToMonth', 2);
    expect($page->get('sheet')['dates'])->toHaveCount(2);

    $page->call('exportSheetPdf')->assertFileDownloaded();
});

it('warns instead of exporting when the section has no records', function () {
    $section = smokeSection();

    Livewire::test(AttendanceRecords::class)
        ->set('sheetSectionId', $section->id)
        ->call('exportSheetPdf')
        ->assertNoFileDownloaded()
        ->assertSuccessful();
});

it('no longer offers an Excel export', function () {
    expect(method_exists(AttendanceRecords::class, 'exportSheet'))->toBeFalse();
});

it('creates a section with the trainer share picked as a fraction', function () {
    [$trainer, $subject] = smokeFixtures();

    Livewire::test(CreateSection::class)
        ->fillForm([
            'name' => 'شعبة الكسر',
            'subject_id' => $subject->id,
            'trainer_id' => $trainer->id,
            'price' => 300,
            'trainer_rate' => TrainerRate::percent('third'),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $section = Section::where('name', 'شعبة الكسر')->firstOrFail();

    expect((float) $section->trainer_rate)->toBe(33.3333)
        ->and($section->effectiveTrainerRate())->toBe(33.3333);
});

it('shows an existing fraction back in the section form', function () {
    $section = smokeSection(['trainer_rate' => TrainerRate::percent('quarter')]);

    Livewire::test(EditSection::class, ['record' => $section->id])
        ->assertSuccessful()
        ->assertFormSet(fn (array $state): bool => (float) $state['trainer_rate'] === 25.0
            && ($state['trainer_rate_fraction'] ?? null) === 'quarter');
});

it('creates and edits a trainer whose default share is a fraction', function () {
    Livewire::test(CreateTrainer::class)
        ->fillForm([
            'name' => ['ar' => 'أستاذ الكسر', 'en' => 'Fraction Trainer'],
            'username' => 'fraction_'.uniqid(),
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'default_rate' => TrainerRate::percent('two_thirds'),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $trainer = Trainer::where('default_rate', 66.6667)->firstOrFail();

    Livewire::test(EditTrainer::class, ['record' => $trainer->id])
        ->assertSuccessful()
        ->assertFormSet(fn (array $state): bool => ($state['default_rate_fraction'] ?? null) === 'two_thirds');
});

it('keeps a plain percentage out of the fraction picker', function () {
    // 37.5% is not one of the presets, so the picker stays empty.
    $section = smokeSection(['trainer_rate' => 37.5]);

    Livewire::test(EditSection::class, ['record' => $section->id])
        ->assertSuccessful()
        ->assertFormSet(fn (array $state): bool => ($state['trainer_rate_fraction'] ?? null) === null);
});

it('does not persist the fraction picker as a column', function () {
    expect(Section::query()->getModel()->getFillable())->not->toContain('trainer_rate_fraction')
        ->and(Schema::hasColumn('sections', 'trainer_rate_fraction'))->toBeFalse();
});

it('renders the student and trainer pages with their schedule calendars', function () {
    $section = smokeSection();
    $studentId = $section->registrations()->first()->student_id;
    $start = substr((string) $section->times->first()->start_time, 0, 5);

    Livewire::test(ViewStudent::class, ['record' => $studentId])
        ->assertSuccessful()
        ->assertSee($start);

    Livewire::test(ViewTrainer::class, ['record' => $section->trainer_id])
        ->assertSuccessful()
        ->assertSee($start);
});

it('keeps the amount due read-only on quick enroll but still saves it', function () {
    $section = smokeSection(['price' => 250]);

    $component = Livewire::test(QuickEnroll::class)->assertSuccessful();

    // The field lives inside the `registrations` repeater, so reach it through
    // that repeater's own row schema rather than the top-level form.
    $repeater = $component->instance()->form->getComponent(
        fn ($component): bool => $component instanceof Repeater
            && $component->getName() === 'registrations',
    );

    expect($repeater)->not->toBeNull();

    $field = collect($repeater->getDefaultChildComponents())
        ->first(fn ($child): bool => $child instanceof TextInput
            && $child->getName() === 'amount_due');

    expect($field)->not->toBeNull()
        // Locked in the UI…
        ->and($field->isDisabled())->toBeTrue()
        // …but still reaches the payload, or the enrolment would save a null fee.
        ->and($field->isDehydrated())->toBeTrue();
});

/**
 * A per-session section keeps `price` at zero and its money in `cycle_fee`, so
 * every screen that read `price` reported it as free.
 */
it('shows the cycle fee as the price of a per-session section', function () {
    $perSession = smokeSection([
        'name' => 'شعبة بالحصة',
        'price' => 0,
        'fee_type' => Section::FEE_TYPE_PER_SESSIONS,
        'sessions_per_cycle' => 8,
        'cycle_fee' => 60,
    ]);

    expect($perSession->displayFee())->toBe(60.0)
        ->and($perSession->feeSummary())->toContain('60.00')
        ->and($perSession->feeSummary())->not->toStartWith('0.00');

    $fixed = smokeSection(['name' => 'شعبة دورة كاملة', 'price' => 500]);

    expect($fixed->displayFee())->toBe(500.0)
        ->and($fixed->feeSummary())->toContain('500.00');

    // …and it reaches the sections table rather than the raw ₪0 column.
    Livewire::test(ListSections::class)
        ->assertSuccessful()
        ->assertSee('60.00')
        ->assertSee('500.00');
});

it('charges one cycle when enrolling into a per-session section', function () {
    $section = smokeSection([
        'price' => 0,
        'fee_type' => Section::FEE_TYPE_PER_SESSIONS,
        'sessions_per_cycle' => 8,
        'cycle_fee' => 60,
    ]);

    // Both enrolment routes agree on what the first charge is.
    expect($section->displayFee())->toBe(60.0);

    $student = Student::create([
        'name' => ['ar' => 'طالب الدورة', 'en' => 'Cycle Student'],
        'username' => 'cycle_'.uniqid(),
        'password' => 'password',
        'status' => 'active',
    ]);

    $registration = Registration::create([
        'student_id' => $student->id,
        'section_id' => $section->id,
        'enrolled_at' => '2026-08-01',
        'amount_due' => $section->displayFee(),
        'amount_paid' => $section->displayFee(),
    ]);

    expect((float) $registration->fresh()->amount_paid)->toBe(60.0)
        ->and($registration->fresh()->paid_through_session)->toBe(8);
});

it('keeps the take-attendance page out of the students navigation group', function () {
    expect(TakeAttendance::getNavigationGroup())->toBeNull()
        ->and(TakeAttendance::getNavigationSort())->toBe(1)
        ->and(AttendanceRecords::getNavigationSort())->toBe(0);
});
