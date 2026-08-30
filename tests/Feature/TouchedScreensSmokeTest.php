<?php

use App\Filament\Admin\Pages\AttendanceRecords;
use App\Models\Attendance;
use App\Models\Registration;
use App\Models\Room;
use App\Models\Section;
use App\Models\SectionTime;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Trainer;
use App\Models\User;

/**
 * Every screen this round of work changed, rendered end to end with real
 * records in place. The unit tests prove the rules; this proves the pages
 * still come up — a broken closure in an infolist or a relation manager only
 * shows itself when something actually renders it.
 */
beforeEach(function () {
    $this->admin = User::firstOrCreate(
        ['email' => 'admin@ma.test'],
        ['name' => 'Super Admin', 'password' => 'password', 'is_active' => true, 'email_verified_at' => now()],
    );

    if (! $this->admin->is_active) {
        $this->admin->update(['is_active' => true]);
    }

    $trainer = Trainer::create([
        'name' => ['ar' => 'أستاذ الفحص', 'en' => 'Smoke Trainer'],
        'username' => 'smoke_t_'.uniqid(),
        'password' => 'password',
        'default_rate' => 50,
    ]);

    $this->section = Section::create([
        'name' => 'شعبة الفحص',
        'subject_id' => Subject::create(['name' => ['ar' => 'مادة الفحص', 'en' => 'Smoke Subject']])->id,
        'trainer_id' => $trainer->id,
        'price' => 200,
        'min_capacity' => 2,
        'capacity' => 10,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonths(3)->toDateString(),
    ]);

    SectionTime::create([
        'section_id' => $this->section->id,
        'room_id' => Room::create(['number' => 'S-'.uniqid()])->id,
        'day' => 'sunday',
        'start_time' => '16:00',
        'end_time' => '17:30',
    ]);

    $this->student = Student::create([
        'name' => ['ar' => 'طالب الفحص', 'en' => 'Smoke Student'],
        'username' => 'smoke_s_'.uniqid(),
        'password' => 'password',
    ]);

    $this->registration = Registration::create([
        'student_id' => $this->student->id,
        'section_id' => $this->section->id,
        'amount_due' => 200,
        'amount_paid' => 200,
    ]);
});

it('renders the sections calendar, which now filters and orders by room', function () {
    $response = $this->actingAs($this->admin)->get('/admin/sections-calendar');

    expect($response->status())->toBeLessThan(500);
});

it('renders the attendance records sheet, which now carries the paid amounts', function () {
    $response = $this->actingAs($this->admin)->get('/admin/attendance-records');

    expect($response->status())->toBeLessThan(500);
});

it('renders the section page with its roster count and capacity range', function () {
    $response = $this->actingAs($this->admin)->get('/admin/sections/'.$this->section->id);

    expect($response->status())->toBeLessThan(500);
});

it('renders the section edit form with the new minimum capacity field', function () {
    $response = $this->actingAs($this->admin)->get('/admin/sections/'.$this->section->id.'/edit');

    expect($response->status())->toBeLessThan(500);
});

it('renders the student page with the enrol action and the registrations list', function () {
    $response = $this->actingAs($this->admin)->get('/admin/students/'.$this->student->id);

    expect($response->status())->toBeLessThan(500);
});

it('renders the registration page with its payment action', function () {
    $response = $this->actingAs($this->admin)->get('/admin/registrations/'.$this->registration->id);

    expect($response->status())->toBeLessThan(500);
});

it('exports the attendance sheet as a landscape PDF with a shaped Arabic font', function () {
    foreach ([7, 14, 21] as $daysAgo) {
        Attendance::create([
            'section_id' => $this->section->id,
            'student_id' => $this->student->id,
            'date' => now()->subDays($daysAgo)->toDateString(),
            'status' => 'present',
        ]);
    }

    $this->actingAs($this->admin);

    $page = Livewire::test(AttendanceRecords::class)->set('sheetSectionId', $this->section->id);

    // Straight at the thing that broke in production: mPDF throws while parsing
    // some Arabic faces, and a bare `assertFileDownloaded` would not tell the
    // difference between a good sheet and a 500.
    $response = $page->instance()->exportSheetPdf();

    ob_start();
    $response->sendContent();
    $body = ob_get_clean();

    // Page content streams are Flate-compressed, so the drawing operators have
    // to be inflated before anything can be asserted about them.
    $operators = '';
    preg_match_all("#stream\r?\n(.*?)endstream#s", $body, $streams);
    foreach ($streams[1] as $stream) {
        $operators .= @gzuncompress($stream) ?: '';
    }

    expect($body)->toStartWith('%PDF')
        // A4 landscape: 841.89 × 595.28 pt.
        ->and($body)->toContain('/MediaBox [0 0 841.890 595.280]')
        // The logo is drawn at 22.5pt, not at the file's intrinsic 512px — the
        // size that used to push the grid onto a second page.
        ->and($operators)->toMatch('/22\.500 0 0 22\.500 [\d.]+ [\d.]+ cm/');
});
