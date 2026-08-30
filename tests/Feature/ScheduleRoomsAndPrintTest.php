<?php

use App\Filament\Admin\Pages\SectionsCalendar;
use App\Models\Room;
use App\Models\Section;
use App\Models\SectionTime;
use App\Models\Subject;
use App\Models\Trainer;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Permission::findOrCreate('section.index', 'web');
    $role = Role::findOrCreate('rooms-calendar-admin', 'web');
    $role->givePermissionTo('section.index');

    $this->admin = User::create([
        'name' => 'Rooms Admin',
        'email' => 'rooms-'.uniqid().'@ma.test',
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
        'is_active' => true,
    ]);
    $this->admin->assignRole($role);

    $this->subject = Subject::create(['name' => ['ar' => 'مادة القاعات', 'en' => 'Rooms Subject']]);

    // Deliberately 1, 2 and 10: a lexical sort puts 10 second, which is the bug
    // the natural ordering exists to avoid.
    $this->room1 = Room::create(['number' => '1']);
    $this->room2 = Room::create(['number' => '2']);
    $this->room10 = Room::create(['number' => '10']);
});

/**
 * A lesson on the coming Monday, in the given room at the given hour.
 *
 * Each one gets its own trainer: several of these overlap on purpose, and a
 * trainer cannot be in two rooms at once — the section rules reject it.
 */
function scheduleLesson(Subject $subject, ?Room $room, string $start, string $name): Section
{
    $trainer = Trainer::create([
        'name' => ['ar' => 'أستاذ '.$name, 'en' => 'Trainer '.$name],
        'username' => 'rooms_trainer_'.uniqid(),
        'password' => 'password',
    ]);

    $section = Section::create([
        'name' => $name,
        'subject_id' => $subject->id,
        'trainer_id' => $trainer->id,
        'price' => 100,
    ]);

    SectionTime::create([
        'section_id' => $section->id,
        'room_id' => $room?->id,
        'day' => 'monday',
        'start_time' => $start,
        'end_time' => Carbon::parse($start)->addHour()->format('H:i'),
    ]);

    return $section;
}

it('orders rooms 1, 2, 10 rather than 1, 10, 2', function () {
    expect(collect(SectionsCalendar::roomOptions())->values()->all())
        ->toBe([__('Room').' 1', __('Room').' 2', __('Room').' 10']);
});

it('puts the earliest lesson first, then walks the rooms in order', function () {
    scheduleLesson($this->subject, $this->room10, '10:00', 'عاشرة عند العاشرة');
    scheduleLesson($this->subject, $this->room2, '09:00', 'ثانية عند التاسعة');
    scheduleLesson($this->subject, $this->room1, '10:00', 'أولى عند العاشرة');
    scheduleLesson($this->subject, null, '09:00', 'بلا قاعة عند التاسعة');

    $page = Livewire::actingAs($this->admin)->test(SectionsCalendar::class);
    $monday = Carbon::parse($page->instance()->periodStart())->next(Carbon::MONDAY);

    $names = $page->instance()->eventsFor($monday)
        ->map(fn (SectionTime $time): string => (string) $time->section?->name)
        ->all();

    expect($names)->toBe([
        // 09:00 first — room 2 before the roomless one, which always trails.
        'ثانية عند التاسعة',
        'بلا قاعة عند التاسعة',
        // then 10:00 — room 1 before room 10.
        'أولى عند العاشرة',
        'عاشرة عند العاشرة',
    ]);
});

it('narrows the calendar to a single room', function () {
    scheduleLesson($this->subject, $this->room1, '09:00', 'درس القاعة الأولى');
    scheduleLesson($this->subject, $this->room2, '09:00', 'درس القاعة الثانية');

    $page = Livewire::actingAs($this->admin)
        ->test(SectionsCalendar::class)
        ->fillForm(['room_id' => $this->room1->id]);

    $monday = Carbon::parse($page->instance()->periodStart())->next(Carbon::MONDAY);

    // Asserted on the grid rather than the markup: the Section picker renders
    // every section's name as an option, filtered or not.
    $names = $page->instance()->eventsFor($monday)
        ->map(fn (SectionTime $time): string => (string) $time->section?->name)
        ->all();

    expect($names)->toBe(['درس القاعة الأولى']);
});

it('builds a weekly grid of rooms against days', function () {
    scheduleLesson($this->subject, $this->room10, '11:00', 'حصة القاعة العاشرة');
    scheduleLesson($this->subject, $this->room1, '08:00', 'حصة القاعة الأولى');

    $page = Livewire::actingAs($this->admin)
        ->test(SectionsCalendar::class)
        ->call('setSpan', SectionsCalendar::VIEW_WEEK);

    $weeks = $page->instance()->weeklyGrid();

    expect($weeks)->toHaveCount(1);

    $week = $weeks[0];

    expect($week['days'])->toHaveCount(7)
        ->and($week['start']->dayOfWeek)->toBe(Carbon::SATURDAY)
        // Only rooms actually used get a row, in walking order.
        ->and(array_column($week['rooms'], 'label'))
        ->toBe([__('Room').' 1', __('Room').' 10']);

    $monday = Carbon::parse($week['start'])->next(Carbon::MONDAY)->toDateString();

    expect($week['rooms'][0]['cells'][$monday][0]['section'])->toBe('حصة القاعة الأولى')
        ->and($week['rooms'][0]['cells'][$monday][0]['time'])->toBe('08:00 – 09:00')
        ->and($week['rooms'][1]['cells'][$monday][0]['section'])->toBe('حصة القاعة العاشرة');
});

it('streams the weekly schedule as a landscape PDF', function () {
    scheduleLesson($this->subject, $this->room1, '08:00', 'حصة للطباعة');

    $page = Livewire::actingAs($this->admin)
        ->test(SectionsCalendar::class)
        ->call('setSpan', SectionsCalendar::VIEW_WEEK);

    $response = $page->instance()->exportWeeklyPdf();

    ob_start();
    $response->sendContent();
    $body = ob_get_clean();

    expect($response->headers->get('content-type'))->toBe('application/pdf')
        ->and($response->headers->get('content-disposition'))
        ->toContain($page->instance()->periodStart()->toDateString())
        ->and($body)->toStartWith('%PDF')
        // A4 landscape: 841.89 × 595.28 pt.
        ->and($body)->toContain('841.890 595.280');
});

it('says nothing to print rather than streaming an empty sheet', function () {
    $page = Livewire::actingAs($this->admin)->test(SectionsCalendar::class);

    expect($page->instance()->exportWeeklyPdf())->toBeNull();
});
