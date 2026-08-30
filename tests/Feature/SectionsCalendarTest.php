<?php

use App\Filament\Admin\Pages\SectionsCalendar;
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
    $role = Role::findOrCreate('calendar-admin', 'web');
    $role->givePermissionTo('section.index');

    $this->admin = User::create([
        'name' => 'Calendar Admin',
        'email' => 'calendar-'.uniqid().'@ma.test',
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
        'is_active' => true,
    ]);
    $this->admin->assignRole($role);

    $this->trainer = Trainer::create([
        'name' => ['ar' => 'أستاذ التقويم', 'en' => 'Calendar Trainer'],
        'username' => 'cal_trainer_'.uniqid(),
        'password' => 'password',
    ]);

    $this->subject = Subject::create(['name' => ['ar' => 'مادة التقويم', 'en' => 'Calendar Subject']]);
});

/** Five sections all meeting on the same weekday, so one cell overflows. */
function crowdTheDay(Subject $subject, Trainer $trainer, int $count = 5): array
{
    $names = [];

    for ($i = 1; $i <= $count; $i++) {
        $section = Section::create([
            'name' => 'شعبة التقويم رقم '.$i,
            'subject_id' => $subject->id,
            'trainer_id' => $trainer->id,
            'price' => 100,
        ]);

        SectionTime::create([
            'section_id' => $section->id,
            'day' => 'monday',
            'start_time' => sprintf('%02d:00', 8 + $i),
            'end_time' => sprintf('%02d:00', 9 + $i),
        ]);

        $names[] = $section->name;
    }

    return $names;
}

it('renders every section of a crowded day, not just the first three', function () {
    $names = crowdTheDay($this->subject, $this->trainer);

    $page = Livewire::actingAs($this->admin)->test(SectionsCalendar::class);

    // The overflow used to be a dead "+2" label; all five have to be in the
    // markup for clicking it to have anything to reveal.
    foreach ($names as $name) {
        $page->assertSee($name);
    }
});

it('turns the overflow counter into a button that expands the day', function () {
    crowdTheDay($this->subject, $this->trainer);

    $html = Livewire::actingAs($this->admin)->test(SectionsCalendar::class)->html();

    expect($html)
        ->toContain('x-on:click="expanded = ! expanded"')
        ->toContain('x-data="{ expanded: false }"')
        // The events past the third are hidden until the button is pressed.
        ->toContain('x-show="expanded"')
        ->toContain('x-cloak');

    // The toggle label is injected as JS, so it must survive as a well-formed
    // attribute rather than breaking out of the quotes.
    expect($html)->toMatch('/x-text="expanded \? [^"]+ : [^"]+"/');
});

it('shows which course a lesson is and who teaches it', function () {
    crowdTheDay($this->subject, $this->trainer, 1);

    Livewire::actingAs($this->admin)
        ->test(SectionsCalendar::class)
        ->assertSee('مادة التقويم')
        ->assertSee('أستاذ التقويم');
});

it('switches between a month, a fortnight and a single week', function () {
    $page = Livewire::actingAs($this->admin)->test(SectionsCalendar::class);

    // A month view is padded to whole weeks, so it is always a multiple of 7
    // and never fewer than four rows.
    $monthDays = count($page->instance()->calendarDays);
    expect($monthDays % 7)->toBe(0)->and($monthDays)->toBeGreaterThanOrEqual(28);

    $page->call('setSpan', SectionsCalendar::VIEW_FORTNIGHT);
    expect(count($page->instance()->calendarDays))->toBe(14);

    $page->call('setSpan', SectionsCalendar::VIEW_WEEK);
    expect(count($page->instance()->calendarDays))->toBe(7);

    // Every grid starts on a Saturday, whatever the span.
    expect($page->instance()->periodStart()->dayOfWeek)->toBe(Carbon::SATURDAY);
});

it('steps by the chosen span instead of always by a month', function () {
    $page = Livewire::actingAs($this->admin)->test(SectionsCalendar::class);

    $page->call('setSpan', SectionsCalendar::VIEW_WEEK);
    $start = $page->instance()->periodStart()->toDateString();

    $page->call('nextPeriod');
    expect($page->instance()->periodStart()->toDateString())
        ->toBe(Carbon::parse($start)->addWeek()->toDateString());

    $page->call('previousPeriod');
    expect($page->instance()->periodStart()->toDateString())->toBe($start);

    $page->call('setSpan', SectionsCalendar::VIEW_FORTNIGHT);
    $fortnightStart = $page->instance()->periodStart()->toDateString();

    $page->call('nextPeriod');
    expect($page->instance()->periodStart()->toDateString())
        ->toBe(Carbon::parse($fortnightStart)->addWeeks(2)->toDateString());
});

it('ignores an unknown span rather than breaking the grid', function () {
    $page = Livewire::actingAs($this->admin)->test(SectionsCalendar::class);

    $page->call('setSpan', 'decade');

    expect($page->instance()->span)->toBe(SectionsCalendar::VIEW_MONTH);
});

it('exports the period on screen, following the chosen span', function () {
    crowdTheDay($this->subject, $this->trainer, 2);

    $page = Livewire::actingAs($this->admin)
        ->test(SectionsCalendar::class)
        ->call('setSpan', SectionsCalendar::VIEW_WEEK)
        ->call('exportPeriod');

    $response = $page->instance()->exportPeriod();

    ob_start();
    $response->sendContent();
    $body = ob_get_clean();

    expect($response->headers->get('content-type'))
        ->toContain('spreadsheetml')
        ->and($response->headers->get('content-disposition'))
        ->toContain($page->instance()->periodStart()->toDateString())
        ->and(strlen($body))->toBeGreaterThan(0);
});
