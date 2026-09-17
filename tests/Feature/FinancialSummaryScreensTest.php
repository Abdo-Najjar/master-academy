<?php

use App\Filament\Admin\Resources\Sections\Pages\ListSections;
use App\Filament\Admin\Resources\Sections\Pages\ViewSection;
use App\Filament\Admin\Resources\Sections\Widgets\SectionFinancialsWidget;
use App\Filament\Admin\Resources\Trainers\Pages\CreateTrainer;
use App\Filament\Admin\Resources\Trainers\Pages\ListTrainers;
use App\Filament\Admin\Resources\Trainers\Pages\ViewTrainer;
use App\Filament\Admin\Resources\Trainers\Widgets\TrainerFinancialsWidget;
use App\Models\Branch;
use App\Models\City;
use App\Models\Governorate;
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
 * The money panels are only true once they render: a stat that throws, a column
 * whose aggregate never reached the query, or a branch box that saves nothing
 * all look fine in isolation and fail on the page.
 */
beforeEach(function () {
    if (! User::query()->whereKey(1)->exists()) {
        User::create([
            'name' => 'Super Admin',
            'email' => 'fin-super@ma.test',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
    }

    $admin = User::create([
        'name' => 'Finance Admin',
        'email' => 'fin-'.uniqid().'@ma.test',
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
        'is_active' => true,
    ]);

    $gates = PermissionCatalog::allGates();

    foreach ($gates as $gate) {
        Permission::firstOrCreate(['name' => $gate, 'guard_name' => 'web']);
    }

    $role = Role::create(['name' => 'fin-'.uniqid(), 'guard_name' => 'web']);
    $role->syncPermissions($gates);
    $admin->assignRole($role);

    $this->actingAs($admin);

    $governorate = Governorate::create(['name' => ['en' => 'Ramallah', 'ar' => 'رام الله']]);
    $this->city = City::create(['governorate_id' => $governorate->id, 'name' => ['en' => 'Ramallah', 'ar' => 'رام الله']]);
    $this->branch = Branch::create([
        'name' => ['en' => 'North', 'ar' => 'فرع الشمال'],
        'governorate_id' => $governorate->id,
        'city_id' => $this->city->id,
    ]);

    $this->trainer = Trainer::create([
        'name' => ['en' => 'Screen Trainer', 'ar' => 'أستاذ'],
        'username' => 'screen_t_'.uniqid(),
        'password' => 'password',
        'default_rate' => 40,
    ]);

    $this->section = Section::create([
        'name' => 'شعبة المالية',
        'subject_id' => Subject::create(['name' => ['en' => 'Subject', 'ar' => 'مادة']])->id,
        'trainer_id' => $this->trainer->id,
        'branch_id' => $this->branch->id,
        'price' => 200,
        'trainer_rate' => 40,
    ]);

    // One student pays in full, the other pays nothing: 400 billed, 200 in,
    // 200 outstanding, and the trainer credited on the funded half only.
    foreach ([200, 0] as $balance) {
        $student = Student::create([
            'name' => ['en' => 'Screen Student', 'ar' => 'طالب'],
            'username' => 'screen_s_'.uniqid(),
            'password' => 'password',
        ]);

        if ($balance > 0) {
            $student->depositFloat($balance, ['description' => 'Opening balance']);
        }

        Registration::create([
            'student_id' => $student->id,
            'section_id' => $this->section->id,
            'amount_due' => 200,
            'amount_paid' => 200,
        ]);
    }
});

it('renders the section financial summary on the section page', function () {
    Livewire::test(SectionFinancialsWidget::class, ['record' => $this->section])
        ->assertSuccessful()
        ->assertSee(__('Financial Summary'))
        ->assertSee(__('Expected'))
        ->assertSee(__('Collected'))
        ->assertSee(__('Outstanding'));
});

it('hangs the section summary off the section view page', function () {
    $widgets = Livewire::test(ViewSection::class, ['record' => $this->section->getKey()])
        ->assertSuccessful()
        ->instance()
        ->getVisibleHeaderWidgets();

    expect($widgets)->not->toBeEmpty();
});

it('sums the section money columns in the sections list query', function () {
    $section = Livewire::test(ListSections::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$this->section])
        ->instance()
        ->getTableRecords()
        ->firstWhere('id', $this->section->getKey());

    // Charged 200 each, only one of them funded.
    expect((float) $section->expected_amount)->toBe(400.0)
        ->and((float) $section->collected_amount)->toBe(200.0);
});

it('sorts the sections list on the outstanding column it computes in PHP', function () {
    // The column has no column behind it — it is the gap between two aggregate
    // aliases — so sorting on it has to survive reaching the database.
    Livewire::test(ListSections::class)
        ->sortTable('outstanding_amount')
        ->assertSuccessful()
        ->sortTable('outstanding_amount', 'desc')
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$this->section]);
});

it('renders the trainer financial summary on the trainer page', function () {
    Livewire::test(TrainerFinancialsWidget::class, ['record' => $this->trainer])
        ->assertSuccessful()
        ->assertSee(__('Financial Summary'))
        ->assertSee(__('Total Credited'))
        ->assertSee(__('Paid Out'))
        ->assertSee(__('Still Owed'))
        ->assertSee(__('Awaiting Collection'));
});

it('hangs the trainer summary off the trainer view page', function () {
    $widgets = Livewire::test(ViewTrainer::class, ['record' => $this->trainer->getKey()])
        ->assertSuccessful()
        ->instance()
        ->getVisibleHeaderWidgets();

    expect($widgets)->not->toBeEmpty();
});

it('saves the branches picked when a trainer is created', function () {
    $username = 'branch_t_'.uniqid();

    Livewire::test(CreateTrainer::class)
        ->fillForm([
            'name' => ['en' => 'Branch Trainer', 'ar' => 'أستاذ الفروع'],
            'username' => $username,
            'password' => 'password',
            'password_confirmation' => 'password',
            'default_rate' => 40,
            'branches' => [$this->branch->id],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $trainer = Trainer::where('username', $username)->firstOrFail();

    expect($trainer->branches()->pluck('branches.id')->all())->toBe([$this->branch->id]);
});

it('filters the trainers list down to one branch', function () {
    $this->trainer->branches()->sync([$this->branch->id]);

    $unattached = Trainer::create([
        'name' => ['en' => 'Other Trainer', 'ar' => 'أستاذ آخر'],
        'username' => 'other_t_'.uniqid(),
        'password' => 'password',
    ]);

    Livewire::test(ListTrainers::class)
        ->assertCanSeeTableRecords([$this->trainer, $unattached])
        ->filterTable('branch', $this->branch->id)
        ->assertCanSeeTableRecords([$this->trainer])
        ->assertCanNotSeeTableRecords([$unattached]);
});
