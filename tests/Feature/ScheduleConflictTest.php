<?php

use App\Filament\Admin\Resources\Registrations\Pages\CreateRegistration;
use App\Filament\Admin\Resources\Sections\Pages\CreateSection;
use App\Models\Registration;
use App\Models\Room;
use App\Models\Section;
use App\Models\SectionTime;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Trainer;
use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * The three double-bookings a centre actually hits:
 *
 * - one room holding two sections at once,
 * - one trainer teaching two sections at once,
 * - one student sitting in two sections at once.
 *
 * The first two are enforced by the section-time observer, so they hold no
 * matter which screen writes the row. The third belongs to the enrolment forms,
 * because it is about a registration rather than a timetable.
 */
function conflictAdmin(): User
{
    if (! User::query()->whereKey(1)->exists()) {
        User::create([
            'name' => 'Super Admin',
            'email' => 'conflict-super@ma.test',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
    }

    $user = User::create([
        'name' => 'Conflict Tester',
        'email' => 'conflict-'.uniqid().'@ma.test',
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
        'is_active' => true,
    ]);

    $gates = PermissionCatalog::allGates();

    foreach ($gates as $gate) {
        Permission::firstOrCreate(['name' => $gate, 'guard_name' => 'web']);
    }

    $role = Role::create(['name' => 'conflict-'.uniqid(), 'guard_name' => 'web']);
    $role->syncPermissions($gates);
    $user->assignRole($role);

    return $user;
}

function conflictTrainer(): Trainer
{
    return Trainer::create([
        'name' => ['ar' => 'أستاذ', 'en' => 'Trainer'],
        'username' => 'clash_t_'.uniqid(),
        'password' => 'password',
        'default_rate' => 40,
    ]);
}

/**
 * A course that is running right now. Dated relative to today on purpose: only
 * a live course holds its trainer and its room, so fixed dates would quietly
 * stop these tests detecting anything once they fell into the past.
 */
function conflictSection(?Trainer $trainer = null, ?string $name = null): Section
{
    return Section::create([
        'name' => $name ?? 'شعبة '.uniqid(),
        'subject_id' => Subject::create(['name' => ['ar' => 'مادة '.uniqid(), 'en' => 'Subject']])->id,
        'trainer_id' => $trainer?->id,
        'price' => 0,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonths(4)->toDateString(),
    ]);
}

/** A course that ended last month: it holds neither its trainer nor its room. */
function finishedSection(?Trainer $trainer = null, ?string $name = null): Section
{
    return Section::create([
        'name' => $name ?? 'دورة منتهية '.uniqid(),
        'subject_id' => Subject::create(['name' => ['ar' => 'مادة '.uniqid(), 'en' => 'Subject']])->id,
        'trainer_id' => $trainer?->id,
        'price' => 0,
        'start_date' => now()->subMonths(4)->toDateString(),
        'end_date' => now()->subMonth()->toDateString(),
    ]);
}

function conflictStudent(): Student
{
    return Student::create([
        'name' => ['ar' => 'طالب', 'en' => 'Student'],
        'username' => 'clash_s_'.uniqid(),
        'password' => 'password',
        'status' => 'active',
    ]);
}

// ---------------------------------------------------------------- rooms

it('refuses to book the same room for two sections at the same time', function () {
    $room = Room::create(['number' => 'R-'.uniqid()]);

    $first = conflictSection(conflictTrainer());
    SectionTime::create([
        'section_id' => $first->id,
        'room_id' => $room->id,
        'day' => 'sunday',
        'start_time' => '16:00',
        'end_time' => '17:30',
    ]);

    // A different trainer, so only the room is in conflict.
    $second = conflictSection(conflictTrainer());

    expect(fn () => SectionTime::create([
        'section_id' => $second->id,
        'room_id' => $room->id,
        'day' => 'sunday',
        'start_time' => '17:00', // overlaps the tail of the first
        'end_time' => '18:30',
    ]))->toThrow(ValidationException::class);

    expect(SectionTime::where('section_id', $second->id)->count())->toBe(0);
});

it('allows the same room back to back, and on another day', function () {
    $room = Room::create(['number' => 'R-'.uniqid()]);

    $first = conflictSection(conflictTrainer());
    SectionTime::create([
        'section_id' => $first->id, 'room_id' => $room->id,
        'day' => 'sunday', 'start_time' => '16:00', 'end_time' => '17:30',
    ]);

    $second = conflictSection(conflictTrainer());

    // Starts exactly when the first ends — not an overlap.
    SectionTime::create([
        'section_id' => $second->id, 'room_id' => $room->id,
        'day' => 'sunday', 'start_time' => '17:30', 'end_time' => '19:00',
    ]);

    // Same hour, different day.
    SectionTime::create([
        'section_id' => $second->id, 'room_id' => $room->id,
        'day' => 'monday', 'start_time' => '16:00', 'end_time' => '17:30',
    ]);

    expect(SectionTime::where('section_id', $second->id)->count())->toBe(2);
});

// -------------------------------------------------------------- trainers

it('refuses to give one trainer two sections at the same time', function () {
    $trainer = conflictTrainer();

    $first = conflictSection($trainer, 'شعبة الصباح');
    SectionTime::create([
        'section_id' => $first->id, 'day' => 'tuesday',
        'start_time' => '10:00', 'end_time' => '11:30',
    ]);

    $second = conflictSection($trainer, 'شعبة أخرى');

    expect(fn () => SectionTime::create([
        'section_id' => $second->id, 'day' => 'tuesday',
        'start_time' => '11:00', 'end_time' => '12:30',
    ]))->toThrow(ValidationException::class);

    expect(SectionTime::where('section_id', $second->id)->count())->toBe(0);
});

it('lets two different trainers teach at the same hour', function () {
    $first = conflictSection(conflictTrainer());
    SectionTime::create([
        'section_id' => $first->id, 'day' => 'tuesday',
        'start_time' => '10:00', 'end_time' => '11:30',
    ]);

    $second = conflictSection(conflictTrainer());
    SectionTime::create([
        'section_id' => $second->id, 'day' => 'tuesday',
        'start_time' => '10:00', 'end_time' => '11:30',
    ]);

    expect(SectionTime::where('section_id', $second->id)->count())->toBe(1);
});

it('lets a section keep its own slot when its times are edited', function () {
    $section = conflictSection(conflictTrainer());

    $time = SectionTime::create([
        'section_id' => $section->id, 'day' => 'wednesday',
        'start_time' => '14:00', 'end_time' => '15:30',
    ]);

    // Re-saving the same row must not read as a clash with itself.
    $time->update(['end_time' => '16:00']);

    expect(substr((string) $time->fresh()->end_time, 0, 5))->toBe('16:00');
});

// -------------------------------------------------------------- students

it('refuses to enroll a student into two sections at the same time', function () {
    $this->actingAs(conflictAdmin());

    $student = conflictStudent();

    $first = conflictSection(conflictTrainer(), 'شعبة الأولى');
    SectionTime::create([
        'section_id' => $first->id, 'day' => 'thursday',
        'start_time' => '16:00', 'end_time' => '17:30',
    ]);

    Registration::create([
        'student_id' => $student->id,
        'section_id' => $first->id,
        'enrolled_at' => '2026-08-01',
        'amount_due' => 0,
        'amount_paid' => 0,
    ]);

    $second = conflictSection(conflictTrainer(), 'شعبة الثانية');
    SectionTime::create([
        'section_id' => $second->id, 'day' => 'thursday',
        'start_time' => '17:00', 'end_time' => '18:30',
    ]);

    Livewire::test(CreateRegistration::class)
        ->fillForm([
            'student_id' => $student->id,
            'section_id' => $second->id,
            'enrolled_at' => '2026-08-01',
            'amount_due' => 0,
            'amount_paid' => 0,
        ])
        ->call('create')
        ->assertHasFormErrors(['section_id']);

    expect(Registration::where('student_id', $student->id)->count())->toBe(1);
});

it('enrolls a student into a second section that does not overlap', function () {
    $this->actingAs(conflictAdmin());

    $student = conflictStudent();

    $first = conflictSection(conflictTrainer(), 'شعبة الأولى');
    SectionTime::create([
        'section_id' => $first->id, 'day' => 'thursday',
        'start_time' => '16:00', 'end_time' => '17:30',
    ]);

    Registration::create([
        'student_id' => $student->id,
        'section_id' => $first->id,
        'enrolled_at' => '2026-08-01',
        'amount_due' => 0,
        'amount_paid' => 0,
    ]);

    $second = conflictSection(conflictTrainer(), 'شعبة الثانية');
    SectionTime::create([
        'section_id' => $second->id, 'day' => 'thursday',
        'start_time' => '18:00', 'end_time' => '19:30',
    ]);

    Livewire::test(CreateRegistration::class)
        ->fillForm([
            'student_id' => $student->id,
            'section_id' => $second->id,
            'enrolled_at' => '2026-08-01',
            'amount_due' => 0,
            'amount_paid' => 0,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Registration::where('student_id', $student->id)->count())->toBe(2);
});

it('does not flag a student clash across different days', function () {
    $this->actingAs(conflictAdmin());

    $student = conflictStudent();

    $first = conflictSection(conflictTrainer());
    SectionTime::create([
        'section_id' => $first->id, 'day' => 'saturday',
        'start_time' => '16:00', 'end_time' => '17:30',
    ]);

    Registration::create([
        'student_id' => $student->id,
        'section_id' => $first->id,
        'enrolled_at' => '2026-08-01',
        'amount_due' => 0,
        'amount_paid' => 0,
    ]);

    $second = conflictSection(conflictTrainer());
    SectionTime::create([
        'section_id' => $second->id, 'day' => 'monday',
        'start_time' => '16:00', 'end_time' => '17:30',
    ]);

    Livewire::test(CreateRegistration::class)
        ->fillForm([
            'student_id' => $student->id,
            'section_id' => $second->id,
            'enrolled_at' => '2026-08-01',
            'amount_due' => 0,
            'amount_paid' => 0,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Registration::where('student_id', $student->id)->count())->toBe(2);
});

// --------------------------------------------------- courses that are over

it('frees the room once the course that held it has finished', function () {
    $room = Room::create(['number' => 'R-'.uniqid()]);

    $over = finishedSection(conflictTrainer());
    SectionTime::create([
        'section_id' => $over->id, 'room_id' => $room->id,
        'day' => 'saturday', 'start_time' => '15:30', 'end_time' => '17:00',
    ]);

    $fresh = conflictSection(conflictTrainer());

    // Exactly the slot the finished course used to sit in.
    SectionTime::create([
        'section_id' => $fresh->id, 'room_id' => $room->id,
        'day' => 'saturday', 'start_time' => '15:30', 'end_time' => '16:30',
    ]);

    expect(SectionTime::where('section_id', $fresh->id)->count())->toBe(1);
});

it('frees the trainer once the course they taught has finished', function () {
    $trainer = conflictTrainer();

    $over = finishedSection($trainer, 'تمريض 1');
    SectionTime::create([
        'section_id' => $over->id, 'day' => 'saturday',
        'start_time' => '15:30', 'end_time' => '17:00',
    ]);

    $fresh = conflictSection($trainer, 'شعبة جديدة');
    SectionTime::create([
        'section_id' => $fresh->id, 'day' => 'saturday',
        'start_time' => '15:30', 'end_time' => '16:30',
    ]);

    expect(SectionTime::where('section_id', $fresh->id)->count())->toBe(1);
});

it('still refuses the slot while the other course is running', function () {
    $trainer = conflictTrainer();

    $running = conflictSection($trainer, 'شعبة قائمة');
    SectionTime::create([
        'section_id' => $running->id, 'day' => 'saturday',
        'start_time' => '15:30', 'end_time' => '17:00',
    ]);

    $another = conflictSection($trainer, 'شعبة تصطدم');

    expect(fn () => SectionTime::create([
        'section_id' => $another->id, 'day' => 'saturday',
        'start_time' => '15:30', 'end_time' => '16:30',
    ]))->toThrow(ValidationException::class);
});

it('lets two courses share a room when their terms never overlap', function () {
    $room = Room::create(['number' => 'R-'.uniqid()]);

    $spring = Section::create([
        'name' => 'دورة الربيع',
        'subject_id' => Subject::create(['name' => ['ar' => 'مادة '.uniqid(), 'en' => 'Subject']])->id,
        'trainer_id' => conflictTrainer()->id,
        'price' => 0,
        'start_date' => now()->addMonth()->toDateString(),
        'end_date' => now()->addMonths(3)->toDateString(),
    ]);

    SectionTime::create([
        'section_id' => $spring->id, 'room_id' => $room->id,
        'day' => 'sunday', 'start_time' => '09:00', 'end_time' => '10:30',
    ]);

    // Starts the day after the first one ends, so the two never coexist.
    $summer = Section::create([
        'name' => 'دورة الصيف',
        'subject_id' => Subject::create(['name' => ['ar' => 'مادة '.uniqid(), 'en' => 'Subject']])->id,
        'trainer_id' => conflictTrainer()->id,
        'price' => 0,
        'start_date' => now()->addMonths(3)->addDay()->toDateString(),
        'end_date' => now()->addMonths(6)->toDateString(),
    ]);

    SectionTime::create([
        'section_id' => $summer->id, 'room_id' => $room->id,
        'day' => 'sunday', 'start_time' => '09:00', 'end_time' => '10:30',
    ]);

    expect(SectionTime::where('section_id', $summer->id)->count())->toBe(1);
});

it('lets a student join a new section at the hour their finished course used', function () {
    $this->actingAs(conflictAdmin());

    $student = conflictStudent();

    $over = finishedSection(conflictTrainer(), 'دورة انتهت');
    SectionTime::create([
        'section_id' => $over->id, 'day' => 'thursday',
        'start_time' => '16:00', 'end_time' => '17:30',
    ]);

    Registration::create([
        'student_id' => $student->id,
        'section_id' => $over->id,
        'enrolled_at' => now()->subMonths(4)->toDateString(),
        'amount_due' => 0,
        'amount_paid' => 0,
    ]);

    $fresh = conflictSection(conflictTrainer(), 'شعبة جديدة');
    SectionTime::create([
        'section_id' => $fresh->id, 'day' => 'thursday',
        'start_time' => '16:00', 'end_time' => '17:30',
    ]);

    Livewire::test(CreateRegistration::class)
        ->fillForm([
            'student_id' => $student->id,
            'section_id' => $fresh->id,
            'enrolled_at' => now()->toDateString(),
            'amount_due' => 0,
            'amount_paid' => 0,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Registration::where('student_id', $student->id)->count())->toBe(2);
});

// ------------------------------------------------ a section against itself

it('refuses two lessons of the same section at the same hour', function () {
    $this->actingAs(conflictAdmin());

    $room = Room::create(['number' => 'R-'.uniqid()]);
    $trainer = conflictTrainer();
    $subject = Subject::create(['name' => ['ar' => 'مادة '.uniqid(), 'en' => 'Subject']]);
    // The section form only offers trainers who teach the chosen course.
    $trainer->subjects()->attach($subject->id);

    Livewire::test(CreateSection::class)
        ->fillForm([
            'name' => 'شعبة تصطدم بنفسها',
            'subject_id' => $subject->id,
            'trainer_id' => $trainer->id,
            'price' => 0,
            'times' => [
                ['day' => 'sunday', 'start_time' => '16:00', 'end_time' => '17:30', 'room_id' => $room->id],
                // Same room, same hour, same section — the students would have
                // to be in both at once.
                ['day' => 'sunday', 'start_time' => '17:00', 'end_time' => '18:00', 'room_id' => $room->id],
            ],
        ])
        ->call('create')
        ->assertHasFormErrors(['times']);

    expect(Section::where('name', 'شعبة تصطدم بنفسها')->exists())->toBeFalse();
});

it('allows a section two lessons back to back, and on another day', function () {
    $this->actingAs(conflictAdmin());

    $room = Room::create(['number' => 'R-'.uniqid()]);
    $trainer = conflictTrainer();
    $subject = Subject::create(['name' => ['ar' => 'مادة '.uniqid(), 'en' => 'Subject']]);
    // The section form only offers trainers who teach the chosen course.
    $trainer->subjects()->attach($subject->id);

    Livewire::test(CreateSection::class)
        ->fillForm([
            'name' => 'شعبة متتالية',
            'subject_id' => $subject->id,
            'trainer_id' => $trainer->id,
            'price' => 0,
            'times' => [
                ['day' => 'sunday', 'start_time' => '16:00', 'end_time' => '17:30', 'room_id' => $room->id],
                // Starts exactly when the first ends — not an overlap.
                ['day' => 'sunday', 'start_time' => '17:30', 'end_time' => '19:00', 'room_id' => $room->id],
                // Same hour as the first, different day.
                ['day' => 'monday', 'start_time' => '16:00', 'end_time' => '17:30', 'room_id' => $room->id],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Section::where('name', 'شعبة متتالية')->firstOrFail()->times()->count())->toBe(3);
});
