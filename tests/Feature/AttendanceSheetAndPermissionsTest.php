<?php

use App\Filament\Admin\Pages\AttendanceRecords;
use App\Filament\Admin\Resources\Students\Actions\WalletActions;
use App\Filament\Admin\Resources\Students\Pages\EditStudent;
use App\Filament\Admin\Resources\Trainers\Pages\EditTrainer;
use App\Filament\Admin\Widgets\OverviewStatsWidget;
use App\Models\Attendance;
use App\Models\Registration;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Trainer;
use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function admin(array $gates = []): User
{
    // User #1 is the super admin, which Gate::before waves through every check —
    // burn that id so permission assertions exercise the real gates.
    if (! User::query()->whereKey(1)->exists()) {
        User::create([
            'name' => 'Super Admin',
            'email' => 'super@ma.test',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
    }

    $user = User::create([
        'name' => 'Tester',
        'email' => 'tester-'.uniqid().'@ma.test',
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
        'is_active' => true,
    ]);

    $gates = $gates === [] ? PermissionCatalog::allGates() : $gates;

    foreach ($gates as $gate) {
        Permission::firstOrCreate(['name' => $gate, 'guard_name' => 'web']);
    }

    $role = Role::create(['name' => 'role-'.uniqid(), 'guard_name' => 'web']);
    $role->syncPermissions($gates);
    $user->assignRole($role);

    return $user;
}

/** A section with one student and two recorded attendance days. */
function sectionWithAttendance(): Section
{
    $subject = Subject::create(['name' => 'Math', 'course_type_id' => null]);

    $section = Section::create([
        'name' => 'Sheet Section',
        'subject_id' => $subject->id,
        'start_date' => now()->subWeek(),
        'end_date' => now()->addWeek(),
        'price' => 100,
        'capacity' => 10,
    ]);

    $student = Student::create([
        'name' => 'Sheet Student',
        'username' => 'sheet-student',
        'password' => Hash::make('password'),
        'status' => 'active',
        'is_active' => true,
        'student_number' => 'STU-SHEET',
        'phone_number' => '0599000111',
    ]);

    Registration::create([
        'student_id' => $student->id,
        'section_id' => $section->id,
        'amount_due' => 100,
        'amount_paid' => 0,
    ]);

    Attendance::create([
        'section_id' => $section->id,
        'student_id' => $student->id,
        'date' => now()->subDays(3)->toDateString(),
        'status' => 'present',
    ]);

    Attendance::create([
        'section_id' => $section->id,
        'student_id' => $student->id,
        'date' => now()->subDays(1)->toDateString(),
        'status' => 'absent',
    ]);

    return $section;
}

test('attendance sheet renders the student x date grid for the chosen section', function () {
    $section = sectionWithAttendance();
    $this->actingAs(admin());

    Livewire::test(AttendanceRecords::class)
        ->set('sheetSectionId', $section->id)
        ->assertSee('Sheet Student')
        ->assertSee('STU-SHEET')
        ->assertSee(now()->subDays(3)->format('d/m'))
        ->assertSee(now()->subDays(1)->format('d/m'))
        ->assertSee('50%'); // one present of two recorded days
});

test('attendance sheet pages 12 sessions at a time and counts each month on its own', function () {
    $section = Section::create([
        'name' => 'Long Section',
        'subject_id' => Subject::create(['name' => 'Chemistry', 'course_type_id' => null])->id,
        'start_date' => now()->subMonths(3),
        'end_date' => now()->addMonth(),
        'price' => 0,
    ]);

    $student = Student::create([
        'name' => 'Monthly Student',
        'username' => 'monthly_'.uniqid(),
        'password' => 'password',
        'student_number' => 'STU-MONTH',
        'phone_number' => '0599123456',
    ]);

    Registration::create([
        'student_id' => $student->id,
        'section_id' => $section->id,
        'amount_due' => 100,
        'amount_paid' => 100,
    ]);

    // 15 lessons: 12 in the first month, 3 in the second. Every lesson of the
    // first month is present, every lesson of the second is absent.
    foreach (range(1, 15) as $i) {
        Attendance::create([
            'section_id' => $section->id,
            'student_id' => $student->id,
            'date' => now()->subDays(40 - $i)->toDateString(),
            'status' => $i <= 12 ? 'present' : 'absent',
        ]);
    }

    $page = new AttendanceRecords;
    $page->sheetSectionId = $section->id;

    $first = $page->sheet();
    expect($first['months'])->toBe(2)
        ->and($first['month'])->toBe(1)
        ->and($first['dates'])->toHaveCount(12)
        ->and($first['allDates'])->toBe(15)
        ->and($first['rows'][0]['counts']['present'])->toBe(12)
        ->and($first['rows'][0]['counts']['absent'])->toBe(0)
        ->and($first['rows'][0]['rate'])->toBe(100.0);

    $page->goToMonth(2);
    $second = $page->sheet();

    expect($second['month'])->toBe(2)
        ->and($second['dates'])->toHaveCount(3)
        // The first month's twelve present days must not leak in here.
        ->and($second['rows'][0]['counts']['present'])->toBe(0)
        ->and($second['rows'][0]['counts']['absent'])->toBe(3)
        ->and($second['rows'][0]['rate'])->toBe(0.0);
});

test('attendance sheet carries the phone number and the financial status', function () {
    $section = sectionWithAttendance();
    $page = new AttendanceRecords;
    $page->sheetSectionId = $section->id;
    $row = $page->sheet()['rows'][0];

    expect($row['phone'])->toBe('0599000111')
        ->and($row['financial_status'])->not->toBeNull();
});

test('attendance sheet only lists dates that actually have attendance', function () {
    $section = sectionWithAttendance();

    $page = new AttendanceRecords;
    $page->sheetSectionId = $section->id;
    $sheet = $page->sheet();

    expect($sheet['dates'])->toBe([
        now()->subDays(3)->toDateString(),
        now()->subDays(1)->toDateString(),
    ]);
    expect($sheet['rows'])->toHaveCount(1);
    expect($sheet['rows'][0]['counts'])->toBe(['present' => 1, 'absent' => 1, 'late' => 0, 'excused' => 0]);
});

test('attendance sheet shows an empty state for a section with no records', function () {
    $section = Section::create([
        'name' => 'Quiet Section',
        'subject_id' => Subject::create(['name' => 'Physics', 'course_type_id' => null])->id,
        'start_date' => now(),
        'end_date' => now()->addWeek(),
        'price' => 0,
        'capacity' => 5,
    ]);
    $this->actingAs(admin());

    Livewire::test(AttendanceRecords::class)
        ->set('sheetSectionId', $section->id)
        ->assertSee(__('No attendance has been recorded for this section yet.'));
});

test('student edit page shows the registrations relation manager', function () {
    $section = sectionWithAttendance();
    $student = Student::first();

    $this->actingAs(admin())
        ->get(EditStudent::getUrl(['record' => $student]))
        ->assertStatus(200)
        ->assertSee(__('Registrations'));
});

test('trainer edit page shows the sections relation manager', function () {
    $trainer = Trainer::create([
        'name' => 'Sheet Trainer',
        'username' => 'sheet-trainer',
        'password' => Hash::make('password'),
        'is_active' => true,
        'trainer_number' => 'TRN-SHEET',
    ]);

    $this->actingAs(admin())
        ->get(EditTrainer::getUrl(['record' => $trainer]))
        ->assertStatus(200)
        ->assertSee(__('Sections'));
});

test('dashboard stats only include the modules a user has permission for', function () {
    $this->actingAs(admin(['student.index']));

    expect(OverviewStatsWidget::canView())->toBeTrue();

    $getStats = (new ReflectionMethod(OverviewStatsWidget::class, 'getStats'));
    $getStats->setAccessible(true);

    $labels = collect($getStats->invoke(new OverviewStatsWidget))
        ->map(fn ($stat) => $stat->getLabel())
        ->all();

    expect($labels)->toContain(__('Active Students'));
    expect($labels)->not->toContain(__('Weekly Revenue'));
    expect($labels)->not->toContain(__('Open Complaints'));
    expect($labels)->not->toContain(__('Sessions Today'));
});

test('dashboard stats widget hides entirely for a user with no matching permission', function () {
    $this->actingAs(admin(['room.index']));

    expect(OverviewStatsWidget::canView())->toBeFalse();
});

test('wallet actions are hidden from users without the wallet permission', function () {
    $this->actingAs(admin(['student.index', 'trainer.index']));

    expect(WalletActions::deposit()->isVisible())->toBeFalse();
    expect(App\Filament\Admin\Resources\Trainers\Actions\WalletActions::withdraw()->isVisible())->toBeFalse();

    $this->actingAs(admin(['student.wallet', 'trainer.wallet']));

    expect(WalletActions::deposit()->isVisible())->toBeTrue();
    expect(App\Filament\Admin\Resources\Trainers\Actions\WalletActions::withdraw()->isVisible())->toBeTrue();
});
