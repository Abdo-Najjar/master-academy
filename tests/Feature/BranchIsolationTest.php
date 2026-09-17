<?php

use App\Filament\Admin\Pages\AuditLog;
use App\Filament\Admin\Pages\GradesRecords;
use App\Filament\Admin\Pages\Reports;
use App\Filament\Admin\Pages\SectionsCalendar;
use App\Filament\Admin\Pages\WalletTransactions;
use App\Filament\Admin\Resources\Branches\BranchResource;
use App\Filament\Admin\Resources\Branches\Pages\ManageBranches;
use App\Filament\Admin\Resources\Expenses\Pages\ManageExpenses;
use App\Filament\Admin\Resources\PaymentTypes\Pages\ManagePaymentTypes;
use App\Filament\Admin\Resources\Registrations\Actions\CollectPaymentAction;
use App\Filament\Admin\Resources\Rooms\Pages\ManageRooms;
use App\Filament\Admin\Resources\Rooms\RoomResource;
use App\Filament\Admin\Resources\Sections\Pages\ListSections;
use App\Filament\Admin\Resources\Sections\SectionResource;
use App\Filament\Admin\Resources\Students\Pages\ListStudents;
use App\Filament\Admin\Resources\Users\Pages\CreateUser;
use App\Filament\Admin\Resources\Users\Pages\EditUser;
use App\Filament\Admin\Resources\Users\Pages\ListUsers;
use App\Filament\Admin\Resources\Users\UserResource;
use App\Filament\Support\BranchField;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\City;
use App\Models\Exam;
use App\Models\ExamGrade;
use App\Models\Expense;
use App\Models\ExpenseType;
use App\Models\Governorate;
use App\Models\LoginActivity;
use App\Models\PaymentType;
use App\Models\Registration;
use App\Models\Room;
use App\Models\RoomBooking;
use App\Models\RoomBookingTime;
use App\Models\Section;
use App\Models\SectionSession;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\SectionWithdrawalService;
use App\Settings\AppSettings;
use App\Support\BranchContext;
use App\Support\PermissionCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * One centre, two sites, and a wall between them.
 *
 * An employee tied to a branch reads their own site and nothing else — its
 * courses, its rooms, its bookings, its money and its staff. Students are the
 * deliberate exception: one person can study at either site, so the student
 * register is shared and every desk sees all of it.
 */
beforeEach(function () {
    // The super admin is exempt from branch scoping by design, and they are
    // whoever holds `app.super_admin_id` — user #1 on a fresh database. Parking
    // a placeholder there keeps the employees below ordinary staff.
    User::create([
        'name' => 'المالك',
        'email' => 'owner-'.uniqid().'@ma.test',
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
        'is_active' => true,
    ]);

    $gates = PermissionCatalog::allGates();

    foreach ($gates as $gate) {
        Permission::firstOrCreate(['name' => $gate, 'guard_name' => 'web']);
    }

    $this->role = Role::create(['name' => 'branch-staff-'.uniqid(), 'guard_name' => 'web']);
    $this->role->syncPermissions($gates);

    $governorate = Governorate::create(['name' => ['ar' => 'رام الله', 'en' => 'Ramallah']]);
    $city = City::create(['governorate_id' => $governorate->id, 'name' => ['ar' => 'رام الله', 'en' => 'Ramallah']]);

    $makeBranch = fn (string $ar, string $en) => Branch::create([
        'name' => ['ar' => $ar, 'en' => $en],
        'governorate_id' => $governorate->id,
        'city_id' => $city->id,
    ]);

    $this->north = $makeBranch('فرع الشمال', 'North');
    $this->south = $makeBranch('فرع الجنوب', 'South');

    $this->subject = Subject::create(['name' => ['ar' => 'اللغة الإنجليزية', 'en' => 'English']]);

    $makeSection = fn (Branch $branch, string $name) => Section::create([
        'name' => $name,
        'subject_id' => $this->subject->id,
        'branch_id' => $branch->id,
        'price' => 400,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonths(3)->toDateString(),
    ]);

    $this->northSection = $makeSection($this->north, 'إنجليزي الشمال');
    $this->southSection = $makeSection($this->south, 'إنجليزي الجنوب');

    $this->northRoom = Room::create(['branch_id' => $this->north->id, 'number' => 'ش-1']);
    $this->southRoom = Room::create(['branch_id' => $this->south->id, 'number' => 'ج-1']);
});

/** An employee who works at one site, or at head office when given none. */
function branchUser(?Branch $branch, Role $role): User
{
    $user = User::create([
        'branch_id' => $branch?->id,
        'name' => 'موظف '.uniqid(),
        'email' => 'staff-'.uniqid().'@ma.test',
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
        'is_active' => true,
    ]);

    $user->assignRole($role);

    return $user;
}

it('shows an employee only their own branch\'s courses and rooms', function () {
    $north = branchUser($this->north, $this->role);

    Livewire::actingAs($north)
        ->test(ListSections::class)
        ->assertCanSeeTableRecords([$this->northSection])
        ->assertCanNotSeeTableRecords([$this->southSection]);

    Livewire::actingAs($north)
        ->test(ManageRooms::class)
        ->assertCanSeeTableRecords([$this->northRoom])
        ->assertCanNotSeeTableRecords([$this->southRoom]);
});

it('lets head office read every branch', function () {
    $head = branchUser(null, $this->role);

    Livewire::actingAs($head)
        ->test(ListSections::class)
        ->assertCanSeeTableRecords([$this->northSection, $this->southSection]);
});

it('never hides a branch from the super admin, even one assigned to a branch', function () {
    // The owner's account. Locking the only person who can undo it out of the
    // rest of the centre is a footgun worth designing away.
    $owner = User::query()->findOrFail((int) config('app.super_admin_id', 1));

    $owner->forceFill(['branch_id' => $this->north->id, 'is_active' => true])->save();
    $owner->assignRole($this->role);

    Livewire::actingAs($owner)
        ->test(ListSections::class)
        ->assertCanSeeTableRecords([$this->northSection, $this->southSection]);
});

it('keeps the student register shared across every branch', function () {
    $north = branchUser($this->north, $this->role);

    $southStudent = Student::create([
        'name' => ['ar' => 'طالب الجنوب', 'en' => 'South Student'],
        'username' => 'south_'.uniqid(),
        'password' => 'password',
        'status' => 'active',
    ]);

    Registration::create([
        'student_id' => $southStudent->id,
        'section_id' => $this->southSection->id,
        'enrolled_at' => now()->subWeek()->toDateString(),
        'amount_due' => 400,
        'amount_paid' => 400,
    ]);

    // The person is shared — one student may study at either site — even though
    // the enrolment that put them there is not.
    Livewire::actingAs($north)
        ->test(ListStudents::class)
        ->assertCanSeeTableRecords([$southStudent]);

    // Signing in as the north employee is what narrows the query, so the
    // unscoped count has to be asked for by name.
    expect(Registration::query()->withoutBranchScope()->count())->toBe(1)
        ->and(Registration::query()->count())->toBe(0);
});

it('walls off everything that hangs off a section', function () {
    $north = branchUser($this->north, $this->role);

    $student = Student::create([
        'name' => ['ar' => 'طالب', 'en' => 'Student'],
        'username' => 'stu_'.uniqid(),
        'password' => 'password',
        'status' => 'active',
    ]);

    foreach ([$this->northSection, $this->southSection] as $section) {
        Registration::create([
            'student_id' => $student->id,
            'section_id' => $section->id,
            'enrolled_at' => now()->subWeek()->toDateString(),
            'amount_due' => 400,
            'amount_paid' => 400,
        ]);

        SectionSession::create([
            'section_id' => $section->id,
            'date' => now()->subDays(2)->toDateString(),
            'type' => SectionSession::TYPE_REGULAR,
            'status' => SectionSession::STATUS_HELD,
        ]);

        Attendance::create([
            'section_id' => $section->id,
            'student_id' => $student->id,
            'date' => now()->subDays(2)->toDateString(),
            'status' => 'present',
        ]);
    }

    expect(Registration::query()->withoutBranchScope()->count())->toBe(2)
        ->and(SectionSession::query()->withoutBranchScope()->count())->toBe(2)
        ->and(Attendance::query()->withoutBranchScope()->count())->toBe(2);

    $this->actingAs($north);

    expect(Registration::query()->count())->toBe(1)
        ->and(Registration::query()->first()->section_id)->toBe($this->northSection->id)
        ->and(SectionSession::query()->count())->toBe(1)
        ->and(Attendance::query()->count())->toBe(1);
});

it('walls off certificates too, since they are issued against a section', function () {
    $north = branchUser($this->north, $this->role);

    $student = Student::create([
        'name' => ['ar' => 'طالب', 'en' => 'Student'],
        'username' => 'cert_'.uniqid(),
        'password' => 'password',
        'status' => 'active',
    ]);

    $template = CertificateTemplate::create([
        'name' => 'قالب',
        'is_active' => true,
        'canvas_width' => 800,
        'canvas_height' => 600,
        'fields_config' => [['key' => 'student_name', 'x' => 100, 'y' => 200, 'font_size' => 24]],
    ]);

    foreach ([$this->northSection, $this->southSection] as $section) {
        Certificate::create([
            'student_id' => $student->id,
            'section_id' => $section->id,
            'template_id' => $template->id,
            'serial_number' => 'CERT-'.uniqid(),
            'issued_at' => now(),
        ]);
    }

    expect(Certificate::query()->withoutBranchScope()->count())->toBe(2);

    $this->actingAs($north);

    expect(Certificate::query()->count())->toBe(1)
        ->and(Certificate::query()->first()->section_id)->toBe($this->northSection->id);
});

it('separates the money: expenses, bookings and their reports', function () {
    $north = branchUser($this->north, $this->role);
    $type = ExpenseType::create(['name' => ['ar' => 'إيجار', 'en' => 'Rent']]);

    foreach ([[$this->north, 1000], [$this->south, 4000]] as [$branch, $amount]) {
        Expense::create([
            'expense_type_id' => $type->id,
            'branch_id' => $branch->id,
            'amount' => $amount,
            'spent_at' => now()->toDateString(),
        ]);
    }

    foreach ([[$this->north, $this->northRoom, 700], [$this->south, $this->southRoom, 2500]] as [$branch, $room, $price]) {
        $booking = RoomBooking::create([
            'room_id' => $room->id,
            'branch_id' => $branch->id,
            'title' => 'حجز '.$branch->id,
            'start_date' => now()->subWeek()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'price' => $price,
        ]);

        RoomBookingTime::create([
            'room_booking_id' => $booking->id,
            'day' => 'monday',
            'start_time' => '18:00',
            'end_time' => '20:00',
        ]);
    }

    Livewire::actingAs($north)->test(ManageExpenses::class)->assertCanSeeTableRecords(
        Expense::query()->withoutBranchScope()->where('branch_id', $this->north->id)->get()
    );

    $reports = Livewire::actingAs($north)->test(Reports::class)->instance();

    expect($reports->getExpenseStatsProperty()['total'])->toBe(1000.0)
        ->and($reports->getExpenseStatsProperty()['count'])->toBe(1)
        ->and($reports->getBookingStatsProperty()['contracted'])->toBe(700.0)
        ->and($reports->getBookingStatsProperty()['count'])->toBe(1);

    // Head office still adds both sites up.
    $head = Livewire::actingAs(branchUser(null, $this->role))->test(Reports::class)->instance();

    expect($head->getExpenseStatsProperty()['total'])->toBe(5000.0)
        ->and($head->getBookingStatsProperty()['contracted'])->toBe(3200.0);
});

it('walls off exam grades, which reach a branch through their exam', function () {
    // Two hops out: a grade belongs to an exam, and the exam to a section.
    // Nothing on the grade row itself names a branch.
    $student = Student::create([
        'name' => ['ar' => 'طالب', 'en' => 'Student'],
        'username' => 'grade_'.uniqid(),
        'password' => 'password',
        'status' => 'active',
    ]);

    foreach ([$this->northSection, $this->southSection] as $section) {
        $exam = Exam::create([
            'section_id' => $section->id,
            'name' => 'امتحان '.$section->name,
            'date' => now()->subWeek()->toDateString(),
            'max_score' => 100,
        ]);

        ExamGrade::create(['exam_id' => $exam->id, 'student_id' => $student->id, 'score' => 90]);
    }

    expect(ExamGrade::query()->withoutBranchScope()->count())->toBe(2);

    $north = branchUser($this->north, $this->role);

    $this->actingAs($north);

    expect(ExamGrade::query()->count())->toBe(1)
        ->and(ExamGrade::query()->first()->exam->section_id)->toBe($this->northSection->id);

    Livewire::actingAs($north)
        ->test(GradesRecords::class)
        ->assertSee('إنجليزي الشمال')
        ->assertDontSee('إنجليزي الجنوب');
});

it('keeps another branch\'s staff out of the login history', function () {
    $north = branchUser($this->north, $this->role);
    $south = branchUser($this->south, $this->role);

    foreach ([$north, $south] as $employee) {
        LoginActivity::create([
            'auth_type' => User::class,
            'auth_id' => $employee->id,
            'ip_address' => '10.0.0.1',
            'logged_in_at' => now(),
        ]);
    }

    // A student signs in too — the register is shared, and so is its history.
    $student = Student::create([
        'name' => ['ar' => 'طالبة', 'en' => 'Student'],
        'username' => 'login_'.uniqid(),
        'password' => 'password',
        'status' => 'active',
    ]);

    LoginActivity::create([
        'auth_type' => Student::class,
        'auth_id' => $student->id,
        'ip_address' => '10.0.0.2',
        'logged_in_at' => now(),
    ]);

    $this->actingAs($north);

    $visible = BranchContext::scopeLoginActivities(LoginActivity::query())->get();

    expect($visible)->toHaveCount(2)
        ->and($visible->where('auth_type', User::class)->pluck('auth_id')->all())->toBe([$north->id])
        ->and($visible->where('auth_type', Student::class))->toHaveCount(1);
});

it('refuses to open another branch\'s record straight from the URL', function () {
    $north = branchUser($this->north, $this->role);
    $this->actingAs($north);

    // Hiding a row from a table is not the same as protecting it: route model
    // binding has to resolve through the same scope, or the wall is one typed
    // address away from being walked around.
    $binding = fn (string $resource) => $resource::getRecordRouteBindingEloquentQuery();

    expect($binding(SectionResource::class)->whereKey($this->southSection->id)->exists())->toBeFalse()
        ->and($binding(SectionResource::class)->whereKey($this->northSection->id)->exists())->toBeTrue()
        ->and($binding(RoomResource::class)->whereKey($this->southRoom->id)->exists())->toBeFalse()
        ->and($binding(RoomResource::class)->whereKey($this->northRoom->id)->exists())->toBeTrue()
        ->and($binding(BranchResource::class)->whereKey($this->south->id)->exists())->toBeFalse()
        ->and($binding(UserResource::class)->whereKey(branchUser($this->south, $this->role)->id)->exists())->toBeFalse()
        ->and($binding(UserResource::class)->whereKey($north->id)->exists())->toBeTrue();
});

it('refuses the printable documents of another branch over HTTP', function () {
    $north = branchUser($this->north, $this->role);

    $student = Student::create([
        'name' => ['ar' => 'طالب', 'en' => 'Student'],
        'username' => 'pdf_'.uniqid(),
        'password' => 'password',
        'status' => 'active',
    ]);

    $make = fn (Section $section) => Registration::create([
        'student_id' => $student->id,
        'section_id' => $section->id,
        'enrolled_at' => now()->subWeek()->toDateString(),
        'amount_due' => 400,
        'amount_paid' => 400,
    ]);

    $northRegistration = $make($this->northSection);
    $southRegistration = $make($this->southSection);

    $this->actingAs($north);

    // The PDF endpoints take an id in the path, so they are a way around the
    // tables if the models do not answer for themselves. They do: implicit
    // route binding resolves through the same scope the screens use.
    $this->get(route('admin.pdf.receipt', ['registration' => $southRegistration->id]))->assertNotFound();
    $this->get(route('admin.pdf.attendance-sheet', ['section' => $this->southSection->id]))->assertNotFound();

    // …and the employee's own branch still prints.
    $this->get(route('admin.pdf.receipt', ['registration' => $northRegistration->id]))->assertOk();

    // The student card is deliberately open: the register is shared.
    $this->get(route('admin.pdf.student-card', ['student' => $student->id]))->assertOk();
});

it('sends an absence alert only to the branch it concerns, plus head office', function () {
    $settings = app(AppSettings::class);
    $settings->enable_absence_alerts = true;
    $settings->absence_alert_threshold = 3;
    $settings->save();

    $northStaff = branchUser($this->north, $this->role);
    $southStaff = branchUser($this->south, $this->role);
    $headOffice = branchUser(null, $this->role);

    $student = Student::create([
        'name' => ['ar' => 'طالب الشمال', 'en' => 'North Student'],
        'username' => 'alert_'.uniqid(),
        'password' => 'password',
        'status' => 'active',
    ]);

    Registration::create([
        'student_id' => $student->id,
        'section_id' => $this->northSection->id,
        'enrolled_at' => now()->subMonth()->toDateString(),
        'amount_due' => 400,
        'amount_paid' => 400,
    ]);

    // The alert names the student and the section they are missing, so it is
    // as much a leak as the section screen would be. It fires from a queued
    // job with nobody signed in, so the branch is read off the section.
    foreach ([3, 2, 1] as $daysAgo) {
        Attendance::create([
            'section_id' => $this->northSection->id,
            'student_id' => $student->id,
            'status' => 'absent',
            'date' => now()->subDays($daysAgo)->toDateString(),
        ]);
    }

    $notified = fn (User $user): int => DB::table('notifications')
        ->where('notifiable_type', User::class)
        ->where('notifiable_id', $user->id)
        ->count();

    expect($notified($northStaff))->toBeGreaterThan(0)
        ->and($notified($headOffice))->toBeGreaterThan(0)
        ->and($notified($southStaff))->toBe(0);
});

it('still shows a student who is enrolled in nothing at all', function () {
    // Registered at the centre, never put in a course — or withdrawn from every
    // one of them. Either way the person is still on the register, and the
    // register is shared.
    $unattached = Student::create([
        'name' => ['ar' => 'طالبة بلا دورة', 'en' => 'Unattached'],
        'username' => 'none_'.uniqid(),
        'password' => 'password',
        'status' => 'active',
    ]);

    foreach ([$this->north, $this->south] as $branch) {
        Livewire::actingAs(branchUser($branch, $this->role))
            ->test(ListStudents::class)
            ->assertCanSeeTableRecords([$unattached]);
    }
});

it('keeps a withdrawn student\'s money on the branch that handled it', function () {
    $north = branchUser($this->north, $this->role);
    $student = Student::create([
        'name' => ['ar' => 'طالب منسحب', 'en' => 'Withdrawn'],
        'username' => 'left_'.uniqid(),
        'password' => 'password',
        'status' => 'active',
    ]);

    $registration = Registration::create([
        'student_id' => $student->id,
        'section_id' => $this->northSection->id,
        'enrolled_at' => now()->subMonth()->toDateString(),
        'amount_due' => 400,
        'amount_paid' => 400,
    ]);

    Livewire::actingAs($north);
    $this->actingAs($north);

    CollectPaymentAction::collect($registration, ['amount' => 400]);

    // Leaving ends the registration but does not delete it, so the history —
    // and the money — stays where it was handled.
    SectionWithdrawalService::withdraw($registration->fresh(), now()->toDateString(), 'سافر');

    $collected = fn (User $user): float => Livewire::actingAs($user)
        ->test(Reports::class)
        ->instance()
        ->getCollectionsProperty()['total'];

    expect($collected($north))->toBe(400.0)
        ->and($collected(branchUser($this->south, $this->role)))->toBe(0.0);
});

it('keeps an advance from an unenrolled student on the branch that took it', function () {
    $north = branchUser($this->north, $this->role);

    $student = Student::create([
        'name' => ['ar' => 'طالب جديد', 'en' => 'Walk-in'],
        'username' => 'advance_'.uniqid(),
        'password' => 'password',
        'status' => 'active',
    ]);

    // The desk takes a deposit before the student picks a course. There is no
    // enrolment to read a branch from, so the movement carries its own.
    $this->actingAs($north);

    $student->depositFloat(500, [
        'description' => 'عربون مقدم',
        'branch_id' => BranchContext::currentBranchId(),
    ]);

    $collected = fn (User $user): float => Livewire::actingAs($user)
        ->test(Reports::class)
        ->instance()
        ->getCollectionsProperty()['total'];

    expect($collected($north))->toBe(500.0)
        ->and($collected(branchUser($this->south, $this->role)))->toBe(0.0)
        ->and($collected(branchUser(null, $this->role)))->toBe(500.0);

    Livewire::actingAs($north)
        ->test(WalletTransactions::class)
        ->assertSee('عربون مقدم');
});

it('refuses to delete a branch that still has staff, rooms or money behind it', function () {
    // Deleting a branch is now much bigger than deleting a label. Its rooms,
    // expenses and bookings would keep pointing at something gone and drop out
    // of everyone's sight, and its employees would be left tied to a branch
    // that no longer exists — signed in, and able to see nothing at all.
    $employee = branchUser($this->north, $this->role);

    Expense::create([
        'expense_type_id' => ExpenseType::create(['name' => ['ar' => 'إيجار', 'en' => 'Rent']])->id,
        'branch_id' => $this->north->id,
        'amount' => 100,
        'spent_at' => now()->toDateString(),
    ]);

    Livewire::actingAs(branchUser(null, $this->role))
        ->test(ManageBranches::class)
        ->callTableAction('delete', $this->north);

    expect($this->north->fresh())->not->toBeNull()
        ->and($employee->fresh()->branch_id)->toBe($this->north->id);
});

it('refuses to delete a payment type that expenses or bookings still use', function () {
    // Payment types used to belong to registrations alone. Money going out and
    // hall instalments name one too, and deleting it would blank the method on
    // records that very much still have one.
    $cash = PaymentType::create(['name' => ['ar' => 'نقداً', 'en' => 'Cash']]);

    Expense::create([
        'expense_type_id' => ExpenseType::create(['name' => ['ar' => 'إيجار', 'en' => 'Rent']])->id,
        'branch_id' => $this->north->id,
        'payment_type_id' => $cash->id,
        'amount' => 100,
        'spent_at' => now()->toDateString(),
    ]);

    Livewire::actingAs(branchUser(null, $this->role))
        ->test(ManagePaymentTypes::class)
        ->callTableAction('delete', $cash);

    expect($cash->fresh())->not->toBeNull();
});

it('keeps the audit trail inside the branch as well', function () {
    // History follows the record. The log is polymorphic — a class name and an
    // id — so nothing joins it to a branch on its own.
    $this->northSection->update(['name' => 'إنجليزي الشمال (معدّل)']);
    $this->southSection->update(['name' => 'إنجليزي الجنوب (معدّل)']);

    $subjects = fn ($query) => $query->where('subject_type', Section::class)
        ->pluck('subject_id')
        ->unique()
        ->sort()
        ->values()
        ->all();

    Livewire::actingAs(branchUser($this->north, $this->role))
        ->test(AuditLog::class)
        ->assertSee('إنجليزي الشمال (معدّل)')
        ->assertDontSee('إنجليزي الجنوب (معدّل)');

    // The raw log still holds both sections — it is the reading of it that
    // narrows, not the writing.
    expect($subjects(Activity::query()))
        ->toBe([$this->northSection->id, $this->southSection->id]);

    $this->actingAs(branchUser($this->north, $this->role));

    expect($subjects(BranchContext::scopeActivityLog(Activity::query())))
        ->toBe([$this->northSection->id]);
});

it('keeps the hand-written report joins inside the branch too', function () {
    // Two report panels are built from raw query builders rather than Eloquent,
    // so no global scope reaches them. They have to filter by branch on their
    // own, and this is what catches it if either stops.
    $student = Student::create([
        'name' => ['ar' => 'طالب', 'en' => 'Student'],
        'username' => 'rep_'.uniqid(),
        'password' => 'password',
        'status' => 'active',
    ]);

    foreach ([$this->northSection, $this->southSection] as $section) {
        Registration::create([
            'student_id' => $student->id,
            'section_id' => $section->id,
            'enrolled_at' => now()->subWeek()->toDateString(),
            'amount_due' => 400,
            'amount_paid' => 400,
        ]);
    }

    $north = Livewire::actingAs(branchUser($this->north, $this->role))->test(Reports::class)->instance();

    expect($north->getSubjectBreakdownProperty()->sum('total'))->toBe(1);

    $head = Livewire::actingAs(branchUser(null, $this->role))->test(Reports::class)->instance();

    expect($head->getSubjectBreakdownProperty()->sum('total'))->toBe(2);
});

it('draws only the branch\'s own lessons and bookings on the calendar', function () {
    $north = branchUser($this->north, $this->role);

    foreach ([[$this->northSection, $this->northRoom], [$this->southSection, $this->southRoom]] as [$section, $room]) {
        $section->times()->create([
            'room_id' => $room->id,
            'day' => 'monday',
            'start_time' => '16:00',
            'end_time' => '18:00',
        ]);
    }

    Livewire::actingAs($north)
        ->test(SectionsCalendar::class)
        ->assertSee('إنجليزي الشمال')
        ->assertDontSee('إنجليزي الجنوب');
});

it('lets head office create an employee who sees every branch', function () {
    Livewire::actingAs(branchUser(null, $this->role))
        ->test(CreateUser::class)
        ->fillForm([
            'branch_id' => BranchField::HEAD_OFFICE,
            'name' => 'مدير عام جديد',
            'email' => 'newhead-'.uniqid().'@ma.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $created = User::query()->where('name', 'مدير عام جديد')->firstOrFail();
    $created->assignRole($this->role);

    expect($created->branch_id)->toBeNull();

    // …and they really do read the whole centre.
    Livewire::actingAs($created->fresh())
        ->test(ListSections::class)
        ->assertCanSeeTableRecords([$this->northSection, $this->southSection]);
});

it('still refuses an employee with no branch chosen at all', function () {
    Livewire::actingAs(branchUser(null, $this->role))
        ->test(CreateUser::class)
        ->fillForm([
            'name' => 'موظف بلا فرع',
            'email' => 'nobranch-'.uniqid().'@ma.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
        ->call('create')
        ->assertHasFormErrors(['branch_id']);

    expect(User::query()->where('name', 'موظف بلا فرع')->exists())->toBeFalse();
});

it('will not let a branch manager mint a head-office account', function () {
    // Handing out an account that reads every other site is handing out more
    // than they hold themselves — so head office is not among their choices,
    // and asking for it anyway is refused rather than quietly accepted.
    Livewire::actingAs(branchUser($this->north, $this->role))
        ->test(CreateUser::class)
        ->fillForm([
            'branch_id' => BranchField::HEAD_OFFICE,
            'name' => 'محاولة ترقية',
            'email' => 'escalate-'.uniqid().'@ma.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
        ->call('create')
        ->assertHasFormErrors(['branch_id']);

    expect(User::query()->where('name', 'محاولة ترقية')->exists())->toBeFalse();
});

it('reads an existing head-office employee back as head office', function () {
    $head = branchUser(null, $this->role);

    $state = Livewire::actingAs(branchUser(null, $this->role))
        ->test(EditUser::class, ['record' => $head->getKey()])
        ->instance()
        ->form
        ->getState()['branch_id'] ?? null;

    expect((int) $state)->toBe(BranchField::HEAD_OFFICE);
});

it('shows an employee only the staff of their own branch', function () {
    $north = branchUser($this->north, $this->role);
    $southStaff = branchUser($this->south, $this->role);
    $headStaff = branchUser(null, $this->role);

    Livewire::actingAs($north)
        ->test(ListUsers::class)
        ->assertCanSeeTableRecords([$north])
        ->assertCanNotSeeTableRecords([$southStaff, $headStaff]);

    // Head office reads the whole staff list.
    Livewire::actingAs($headStaff)
        ->test(ListUsers::class)
        ->assertCanSeeTableRecords([$north, $southStaff, $headStaff]);
});

it('keeps a branch out of the other branch\'s payment operations', function () {
    $north = branchUser($this->north, $this->role);

    $southStudent = Student::create([
        'name' => ['ar' => 'طالب الجنوب', 'en' => 'South Student'],
        'username' => 'south_pay_'.uniqid(),
        'password' => 'password',
        'status' => 'active',
    ]);

    Registration::create([
        'student_id' => $southStudent->id,
        'section_id' => $this->southSection->id,
        'enrolled_at' => now()->subWeek()->toDateString(),
        'amount_due' => 400,
        'amount_paid' => 400,
    ]);

    $southStudent->depositFloat(400, ['description' => 'رسوم الجنوب']);

    // A wallet has no branch of its own, so it is read through where the
    // student actually studies — and this one studies at the other site.
    Livewire::actingAs($north)
        ->test(WalletTransactions::class)
        ->assertSee(__('No records found'));

    $collections = Livewire::actingAs($north)->test(Reports::class)->instance()->getCollectionsProperty();

    expect($collections['total'])->toBe(0.0);

    $head = Livewire::actingAs(branchUser(null, $this->role))->test(Reports::class)->instance();

    expect($head->getCollectionsProperty()['total'])->toBe(400.0);
});

it('shows an employee only their own branch in the branches screen', function () {
    Livewire::actingAs(branchUser($this->north, $this->role))
        ->test(ManageBranches::class)
        ->assertCanSeeTableRecords([$this->north])
        ->assertCanNotSeeTableRecords([$this->south]);
});

it('still blocks a clash with the other branch, which the employee cannot see', function () {
    // Messy data: a south course meeting in a north room. The north employee
    // cannot see that course at all, and must still be stopped from booking the
    // hall over it — a room is a physical space, not a branch's property.
    $this->southSection->times()->create([
        'room_id' => $this->northRoom->id,
        'day' => 'monday',
        'start_time' => '16:00',
        'end_time' => '18:00',
    ]);

    $this->actingAs(branchUser($this->north, $this->role));

    expect(Section::query()->count())->toBe(1);

    $booking = RoomBooking::create([
        'room_id' => $this->northRoom->id,
        'branch_id' => $this->north->id,
        'title' => 'ورشة الشمال',
        'start_date' => now()->subWeek()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'price' => 500,
    ]);

    try {
        RoomBookingTime::create([
            'room_booking_id' => $booking->id,
            'day' => 'monday',
            'start_time' => '17:00',
            'end_time' => '19:00',
        ]);

        $message = null;
    } catch (ValidationException $e) {
        $message = collect($e->errors())->flatten()->implode(' ');
    }

    // Refused — and without naming the other site's course, which this
    // employee has no business reading.
    expect($message)->toBe(__('The room is taken on :day at :time by another branch.', [
        'day' => __('Monday'),
        'time' => '16:00 - 18:00',
    ]))
        ->and($message)->not->toContain('إنجليزي الجنوب')
        ->and($booking->times()->count())->toBe(0);
});

it('offers an employee only their own branch to file a record under', function () {
    $this->actingAs(branchUser($this->north, $this->role));

    expect(BranchContext::currentBranchId())->toBe($this->north->id)
        ->and(BranchContext::isRestricted())->toBeTrue()
        ->and(array_keys(BranchContext::selectableBranches()))->toBe([$this->north->id])
        ->and(BranchContext::defaultBranchId())->toBe($this->north->id);

    $this->actingAs(branchUser(null, $this->role));

    expect(BranchContext::currentBranchId())->toBeNull()
        ->and(BranchContext::isRestricted())->toBeFalse()
        ->and(array_keys(BranchContext::selectableBranches()))
        ->toEqualCanonicalizing([$this->north->id, $this->south->id]);
});

it('leaves the portals alone — a trainer signing in is not an employee', function () {
    // The scopes hang off the admin guard. A trainer or a student on their own
    // portal is a different model on a different guard, and none of this
    // applies to them.
    expect(BranchContext::currentBranchId())->toBeNull();

    $this->actingAs(branchUser($this->north, $this->role));

    expect(BranchContext::currentBranchId())->toBe($this->north->id);

    auth('web')->logout();

    expect(BranchContext::currentBranchId())->toBeNull()
        ->and(Section::query()->count())->toBe(2);
});
