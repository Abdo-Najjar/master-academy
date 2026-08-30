<?php

use App\Livewire\ScheduleCalendar;
use App\Models\Section;
use App\Models\SectionTime;
use App\Models\Subject;
use Livewire\Livewire;

it('renders the lessons of the given sections and moves between months', function () {
    $section = Section::create([
        'name' => 'شعبة التقويم',
        'subject_id' => Subject::create(['name' => ['ar' => 'حاسوب', 'en' => 'Computing']])->id,
        'price' => 0,
        'start_date' => now()->startOfMonth(),
        'end_date' => now()->addMonths(3),
    ]);

    SectionTime::create([
        'section_id' => $section->id,
        'day' => 'sunday',
        'start_time' => '16:00',
        'end_time' => '17:30',
    ]);

    Livewire::test(ScheduleCalendar::class, ['sectionIds' => [$section->id]])
        ->assertSuccessful()
        ->assertSee('16:00')
        ->call('shiftMonth', 1)
        ->assertSuccessful()
        ->call('goToday')
        ->assertSet('cursor', now()->startOfMonth()->toDateString());
});

it('renders an empty state when there are no sections', function () {
    Livewire::test(ScheduleCalendar::class, ['sectionIds' => []])
        ->assertSuccessful()
        ->assertSee(__('No sections'));
});
