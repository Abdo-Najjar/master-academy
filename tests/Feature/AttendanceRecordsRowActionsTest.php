<?php

use App\Filament\Admin\Pages\AttendanceRecords;
use App\Models\Attendance;
use App\Models\PaymentType;
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

/**
 * The attendance sheet is where the desk actually stands when a student turns
 * up and pays, so the row carries the two things they reach for: the student's
 * page, and the bill for the section on screen.
 */
function attendanceSheetAdmin(array $gates): User
{
    if (! User::query()->whereKey(1)->exists()) {
        User::create([
            'name' => 'Super Admin',
            'email' => 'sheet-super@ma.test',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
    }

    $admin = User::create([
        'name' => 'Sheet Admin',
        'email' => 'sheet-'.uniqid().'@ma.test',
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
        'is_active' => true,
    ]);

    foreach (PermissionCatalog::allGates() as $gate) {
        Permission::firstOrCreate(['name' => $gate, 'guard_name' => 'web']);
    }

    $role = Role::create(['name' => 'sheet-'.uniqid(), 'guard_name' => 'web']);
    $role->syncPermissions($gates);
    $admin->assignRole($role);

    return $admin;
}

beforeEach(function () {
    $this->admin = attendanceSheetAdmin(PermissionCatalog::allGates());
    $this->actingAs($this->admin);

    $trainer = Trainer::create([
        'name' => ['ar' => 'أستاذ', 'en' => 'Trainer'],
        'username' => 'sheet_t_'.uniqid(),
        'password' => 'password',
        'default_rate' => 50,
    ]);

    $this->section = Section::create([
        'name' => 'شعبة الحضور',
        'subject_id' => Subject::create(['name' => ['ar' => 'مادة', 'en' => 'Subject']])->id,
        'trainer_id' => $trainer->id,
        'price' => 60,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonths(4)->toDateString(),
    ]);

    $this->student = Student::create([
        'name' => ['ar' => 'طالب', 'en' => 'Student'],
        'username' => 'sheet_s_'.uniqid(),
        'password' => 'password',
    ]);

    // Charged 60, nothing banked — the row the desk collects against.
    $this->registration = Registration::create([
        'student_id' => $this->student->id,
        'section_id' => $this->section->id,
        'enrolled_at' => now()->subMonth()->toDateString(),
        'amount_due' => 60,
        'amount_paid' => 60,
    ]);

    Attendance::create([
        'student_id' => $this->student->id,
        'section_id' => $this->section->id,
        'date' => now()->subWeek()->toDateString(),
        'status' => 'present',
    ]);
});

it('points each row at the student page and at the bill for the section on screen', function () {
    $sheet = Livewire::test(AttendanceRecords::class)
        ->set('sheetSectionId', $this->section->id)
        ->instance()
        ->sheet;

    $row = collect($sheet['rows'])->firstWhere('student.id', $this->student->id);

    expect($row)->not->toBeNull()
        ->and($row['registration_id'])->toBe($this->registration->id)
        ->and($row['student_url'])->toContain((string) $this->student->id);
});

it('collects a payment from the sheet and shows the new balance on the same row', function () {
    $type = PaymentType::create(['name' => 'Cash '.uniqid()]);

    $component = Livewire::test(AttendanceRecords::class)
        ->set('sheetSectionId', $this->section->id)
        ->callAction('collectPayment', [
            'amount' => 60,
            'payment_type_id' => $type->id,
        ], ['registration' => $this->registration->id]);

    $component->assertHasNoActionErrors();

    $this->registration->refresh();

    expect((float) $this->registration->funded_amount)->toBe(60.0)
        ->and($this->student->refresh()->balanceFloat)->toEqual(0.0);

    // The pill and the paid/remaining line are computed, so the row has to
    // report the payment rather than the balance from before it.
    $row = collect($component->instance()->sheet['rows'])
        ->firstWhere('student.id', $this->student->id);

    expect((float) $row['paid'])->toBe(60.0)
        ->and((float) $row['remaining'])->toBe(0.0);
});

it('refuses to collect against a registration from another section', function () {
    $other = Section::create([
        'name' => 'شعبة أخرى',
        'subject_id' => Subject::create(['name' => ['ar' => 'مادة', 'en' => 'Subject']])->id,
        'trainer_id' => $this->section->trainer_id,
        'price' => 100,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonths(4)->toDateString(),
    ]);

    $foreign = Registration::create([
        'student_id' => $this->student->id,
        'section_id' => $other->id,
        'enrolled_at' => now()->subMonth()->toDateString(),
        'amount_due' => 100,
        'amount_paid' => 100,
    ]);

    Livewire::test(AttendanceRecords::class)
        ->set('sheetSectionId', $this->section->id)
        ->callAction('collectPayment', ['amount' => 100], ['registration' => $foreign->id]);

    expect((float) $foreign->refresh()->funded_amount)->toBe(0.0);
});

it('hides the collect button from a reader without the collect permission', function () {
    $reader = attendanceSheetAdmin(['attendance.index', 'student.index']);

    $this->actingAs($reader);

    Livewire::test(AttendanceRecords::class)
        ->set('sheetSectionId', $this->section->id)
        ->assertActionHidden('collectPayment');
});
