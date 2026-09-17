<?php

use App\Filament\Admin\Pages\Reports;
use App\Filament\Admin\Pages\SectionsCalendar;
use App\Filament\Admin\Resources\RoomBookings\Actions\CollectBookingPaymentAction;
use App\Filament\Admin\Resources\RoomBookings\Pages\CreateRoomBooking;
use App\Filament\Support\SectionEnrolmentRules;
use App\Models\PaymentType;
use App\Models\Registration;
use App\Models\Room;
use App\Models\RoomBooking;
use App\Models\RoomBookingTime;
use App\Models\Section;
use App\Models\SectionTime;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Trainer;
use App\Models\User;
use App\Support\PermissionCatalog;
use App\Support\ReceiptAttachment;
use App\Support\RoomScheduleLock;
use Carbon\Carbon;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Letting a hall out to someone who is not a section.
 *
 * A booking holds a room exactly as firmly as a lesson does, so whichever of
 * the two is entered second has to be refused — and the general calendar has to
 * show both, or the desk will keep promising a room that is already taken.
 */
function bookingAdmin(): User
{
    $user = User::create([
        'name' => 'Booking Admin',
        'email' => 'booking-'.uniqid().'@ma.test',
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
        'is_active' => true,
    ]);

    $gates = PermissionCatalog::allGates();

    foreach ($gates as $gate) {
        Permission::firstOrCreate(['name' => $gate, 'guard_name' => 'web']);
    }

    $role = Role::create(['name' => 'booking-'.uniqid(), 'guard_name' => 'web']);
    $role->syncPermissions($gates);
    $user->assignRole($role);

    return $user;
}

/** A live course in the given room, dated around today so it holds the room. */
function bookingRivalSection(Room $room, string $day, string $start, string $end): Section
{
    $section = Section::create([
        'name' => 'شعبة '.uniqid(),
        'subject_id' => Subject::create(['name' => ['ar' => 'مادة '.uniqid(), 'en' => 'Subject']])->id,
        'trainer_id' => Trainer::create([
            'name' => ['ar' => 'أستاذ', 'en' => 'Trainer'],
            'username' => 'book_t_'.uniqid(),
            'password' => 'password',
        ])->id,
        'price' => 0,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonths(4)->toDateString(),
    ]);

    SectionTime::create([
        'section_id' => $section->id,
        'room_id' => $room->id,
        'day' => $day,
        'start_time' => $start,
        'end_time' => $end,
    ]);

    return $section;
}

/** A booking running for the next month, with one weekly slot. */
function makeBooking(Room $room, string $day, string $start, string $end, array $attributes = []): RoomBooking
{
    $booking = RoomBooking::create(array_merge([
        'room_id' => $room->id,
        'title' => 'ورشة عمل '.uniqid(),
        'client_name' => 'جهة خارجية',
        'start_date' => now()->subWeek()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'price' => 1000,
        'status' => RoomBooking::STATUS_CONFIRMED,
    ], $attributes));

    RoomBookingTime::create([
        'room_booking_id' => $booking->id,
        'day' => $day,
        'start_time' => $start,
        'end_time' => $end,
    ]);

    return $booking->refresh();
}

/** The next occurrence of a weekday, as the calendar cursor would land on it. */
function nextWeekday(string $day): Carbon
{
    $date = now()->startOfDay();

    while (strtolower($date->format('l')) !== strtolower($day)) {
        $date->addDay();
    }

    return $date;
}

// ------------------------------------------------------------ conflicts

it('refuses to book a hall over a lesson already in that room', function () {
    $room = Room::create(['number' => 'B-'.uniqid()]);
    bookingRivalSection($room, 'monday', '16:00', '17:30');

    $booking = RoomBooking::create([
        'room_id' => $room->id,
        'title' => 'حجز متعارض',
        'start_date' => now()->subWeek()->toDateString(),
        'end_date' => now()->addMonth()->toDateString(),
        'price' => 0,
    ]);

    expect(fn () => RoomBookingTime::create([
        'room_booking_id' => $booking->id,
        'day' => 'monday',
        'start_time' => '17:00', // overlaps the tail of the lesson
        'end_time' => '18:30',
    ]))->toThrow(ValidationException::class);

    expect(RoomBookingTime::where('room_booking_id', $booking->id)->count())->toBe(0);
});

it('refuses to schedule a lesson in a hall that is already booked', function () {
    $room = Room::create(['number' => 'B-'.uniqid()]);
    makeBooking($room, 'tuesday', '10:00', '13:00');

    $section = Section::create([
        'name' => 'شعبة متأخرة',
        'subject_id' => Subject::create(['name' => ['ar' => 'مادة', 'en' => 'Subject']])->id,
        'price' => 0,
        'start_date' => now()->subWeek()->toDateString(),
        'end_date' => now()->addMonths(2)->toDateString(),
    ]);

    expect(fn () => SectionTime::create([
        'section_id' => $section->id,
        'room_id' => $room->id,
        'day' => 'tuesday',
        'start_time' => '12:00',
        'end_time' => '14:00',
    ]))->toThrow(ValidationException::class);

    expect(SectionTime::where('section_id', $section->id)->count())->toBe(0);
});

it('allows back-to-back slots whichever order they are entered in', function () {
    // The columns hold a mix of "12:00" and "12:00:00", and compared as text
    // '12:00' < '12:00:00' is true — the shorter string sorts first. That made
    // a lesson ending at noon clash with the one starting at noon, so a room
    // could not be used twice in a day without a gap.
    $room = Room::create(['number' => 'B-'.uniqid()]);

    // Written the way a seeder or an import does it: no seconds.
    $section = bookingRivalSection($room, 'thursday', '12:00', '14:00');

    // Ends exactly when the lesson begins — the case that used to be refused.
    $before = makeBooking($room, 'thursday', '10:00', '12:00');

    // Begins exactly when the lesson ends.
    $after = makeBooking($room, 'thursday', '14:00', '16:00');

    expect($before->times()->count())->toBe(1)
        ->and($after->times()->count())->toBe(1);

    // And a lesson may still be slotted in against an existing booking's edge.
    SectionTime::create([
        'section_id' => $section->id,
        'room_id' => $room->id,
        'day' => 'thursday',
        'start_time' => '16:00',
        'end_time' => '18:00',
    ]);

    expect(SectionTime::where('section_id', $section->id)->count())->toBe(2);

    // A genuine overlap is still refused, seconds or no seconds.
    expect(fn () => SectionTime::create([
        'section_id' => $section->id,
        'room_id' => $room->id,
        'day' => 'thursday',
        'start_time' => '11:00:00',
        'end_time' => '13:00:00',
    ]))->toThrow(ValidationException::class);
});

it('lets a student and a trainer take back-to-back courses', function () {
    // The same HH:MM-vs-HH:MM:SS comparison guarded the student's timetable and
    // the trainer's, so a course ending at noon looked like it clashed with one
    // starting at noon — for the person as well as for the room.
    $trainer = Trainer::create([
        'name' => ['ar' => 'أستاذ', 'en' => 'Trainer'],
        'username' => 'btb_'.uniqid(),
        'password' => 'password',
    ]);

    $subject = Subject::create(['name' => ['ar' => 'مادة', 'en' => 'Subject']]);

    $make = function (string $name, string $start, string $end, bool $sameTrainer = true) use ($trainer, $subject): Section {
        $section = Section::create([
            'name' => $name,
            'subject_id' => $subject->id,
            'trainer_id' => $sameTrainer ? $trainer->id : null,
            'price' => 0,
            'start_date' => now()->subWeek()->toDateString(),
            'end_date' => now()->addMonths(2)->toDateString(),
        ]);

        SectionTime::create([
            'section_id' => $section->id,
            'day' => 'sunday',
            'start_time' => $start,
            'end_time' => $end,
        ]);

        return $section;
    };

    $morning = $make('الصباحية', '10:00', '12:00');
    $noon = $make('الظهيرة', '12:00', '14:00');

    // The trainer teaches both, back to back — which is an ordinary timetable.
    expect(SectionTime::whereIn('section_id', [$morning->id, $noon->id])->count())->toBe(2);

    // And a student may sit in both without being told they clash.
    $student = Student::create([
        'name' => ['ar' => 'طالب', 'en' => 'Student'],
        'username' => 'btb_s_'.uniqid(),
        'password' => 'password',
        'status' => 'active',
    ]);

    Registration::create([
        'student_id' => $student->id,
        'section_id' => $morning->id,
        'enrolled_at' => now()->subDay()->toDateString(),
    ]);

    $clash = null;
    SectionEnrolmentRules::noScheduleClash($student->id)(
        'section_id',
        $noon->id,
        function (string $message) use (&$clash): void {
            $clash = $message;
        },
    );

    expect($clash)->toBeNull();

    // A genuine overlap is still caught. Someone else teaches it — the trainer
    // clash is a separate rule and would fire first otherwise.
    $overlapping = $make('متداخلة', '13:00', '15:00', sameTrainer: false);

    SectionEnrolmentRules::noScheduleClash($student->id)(
        'section_id',
        $overlapping->id,
        function (string $message) use (&$clash): void {
            $clash = $message;
        },
    );

    expect($clash)->toBeNull();

    Registration::create([
        'student_id' => $student->id,
        'section_id' => $noon->id,
        'enrolled_at' => now()->subDay()->toDateString(),
    ]);

    SectionEnrolmentRules::noScheduleClash($student->id)(
        'section_id',
        $overlapping->id,
        function (string $message) use (&$clash): void {
            $clash = $message;
        },
    );

    expect($clash)->not->toBeNull();
});

it('holds the room while it writes, and always gives it back', function () {
    // The clash check reads the timetable and the row is inserted after, so the
    // room is held across both. A leaked lock would wedge that hall for
    // everyone, so the release matters as much as the hold.
    $room = Room::create(['number' => 'B-'.uniqid()]);

    expect(RoomScheduleLock::isFree($room->id))->toBeTrue();

    $booking = makeBooking($room, 'sunday', '09:00', '11:00');

    expect(RoomScheduleLock::isFree($room->id))->toBeTrue();

    // A refused save must free it too, not only a successful one.
    expect(fn () => RoomBookingTime::create([
        'room_booking_id' => $booking->id,
        'day' => 'sunday',
        'start_time' => '10:00',
        'end_time' => '12:00',
    ]))->toThrow(ValidationException::class);

    expect(RoomScheduleLock::isFree($room->id))->toBeTrue();

    // …and so must a refused lesson.
    $section = Section::create([
        'name' => 'شعبة القفل',
        'subject_id' => Subject::create(['name' => ['ar' => 'مادة', 'en' => 'Subject']])->id,
        'price' => 0,
        'start_date' => now()->subWeek()->toDateString(),
        'end_date' => now()->addMonths(2)->toDateString(),
    ]);

    expect(fn () => SectionTime::create([
        'section_id' => $section->id,
        'room_id' => $room->id,
        'day' => 'sunday',
        'start_time' => '10:00',
        'end_time' => '12:00',
    ]))->toThrow(ValidationException::class);

    expect(RoomScheduleLock::isFree($room->id))->toBeTrue();

    // A lesson with no room takes no lock at all.
    SectionTime::create([
        'section_id' => $section->id,
        'day' => 'friday',
        'start_time' => '09:00',
        'end_time' => '10:00',
    ]);

    expect(SectionTime::where('section_id', $section->id)->count())->toBe(1);
});

it('refuses a second writer that cannot get the room in time', function () {
    // Simulating the race directly: someone else already holds this hall, and
    // this save must be told so rather than reading a timetable mid-change.
    $room = Room::create(['number' => 'B-'.uniqid()]);
    $booking = makeBooking($room, 'tuesday', '09:00', '11:00');

    $rival = Cache::lock('room-schedule:'.$room->id, 30);
    expect($rival->get())->toBeTrue();

    try {
        expect(fn () => RoomBookingTime::create([
            'room_booking_id' => $booking->id,
            'day' => 'wednesday',
            'start_time' => '09:00',
            'end_time' => '11:00',
        ]))->toThrow(ValidationException::class);
    } finally {
        $rival->release();
    }

    // Once the other writer is done, the same slot goes in cleanly.
    RoomBookingTime::create([
        'room_booking_id' => $booking->id,
        'day' => 'wednesday',
        'start_time' => '09:00',
        'end_time' => '11:00',
    ]);

    expect($booking->times()->count())->toBe(2);
})->skip(fn (): bool => ! Cache::getStore() instanceof LockProvider,
    'The configured cache store cannot hold locks.');

it('lets a hall be booked around a lesson it does not overlap', function () {
    $room = Room::create(['number' => 'B-'.uniqid()]);
    bookingRivalSection($room, 'monday', '16:00', '17:30');

    $booking = makeBooking($room, 'monday', '18:00', '20:00');

    expect($booking->times()->count())->toBe(1);
});

it('frees the room once a booking is cancelled', function () {
    $room = Room::create(['number' => 'B-'.uniqid()]);
    makeBooking($room, 'wednesday', '09:00', '11:00', ['status' => RoomBooking::STATUS_CANCELLED]);

    $section = Section::create([
        'name' => 'شعبة بعد الإلغاء',
        'subject_id' => Subject::create(['name' => ['ar' => 'مادة', 'en' => 'Subject']])->id,
        'price' => 0,
        'start_date' => now()->subWeek()->toDateString(),
        'end_date' => now()->addMonths(2)->toDateString(),
    ]);

    SectionTime::create([
        'section_id' => $section->id,
        'room_id' => $room->id,
        'day' => 'wednesday',
        'start_time' => '09:30',
        'end_time' => '10:30',
    ]);

    expect(SectionTime::where('section_id', $section->id)->count())->toBe(1);
});

it('re-checks a booking\'s slots when it is moved to another room', function () {
    $busy = Room::create(['number' => 'B-'.uniqid()]);
    $free = Room::create(['number' => 'B-'.uniqid()]);

    bookingRivalSection($busy, 'thursday', '10:00', '12:00');
    $booking = makeBooking($free, 'thursday', '10:00', '12:00');

    // Moving the booking is as capable of double-booking as adding a slot.
    expect(fn () => $booking->update(['room_id' => $busy->id]))
        ->toThrow(ValidationException::class);

    expect($booking->fresh()->room_id)->toBe($free->id);
});

it('refuses two slots of the same booking that overlap each other', function () {
    $room = Room::create(['number' => 'B-'.uniqid()]);
    $booking = makeBooking($room, 'saturday', '18:30', '21:00');

    // `occupant()` looks past the whole booking so it does not collide with the
    // row being edited, which leaves it blind to the booking's own siblings —
    // one hall cannot hold the same event twice at once.
    expect(fn () => RoomBookingTime::create([
        'room_booking_id' => $booking->id,
        'day' => 'saturday',
        'start_time' => '20:00',
        'end_time' => '22:00',
    ]))->toThrow(ValidationException::class);

    expect($booking->times()->count())->toBe(1);

    // A slot that merely touches the first one is fine: 21:00 is the end.
    RoomBookingTime::create([
        'room_booking_id' => $booking->id,
        'day' => 'saturday',
        'start_time' => '21:00',
        'end_time' => '22:00',
    ]);

    expect($booking->times()->count())->toBe(2);
});

it('stops holding the room once the booking is deleted', function () {
    $room = Room::create(['number' => 'B-'.uniqid()]);
    $booking = makeBooking($room, 'friday', '09:00', '12:00');

    $booking->delete();

    $section = Section::create([
        'name' => 'شعبة بعد الحذف',
        'subject_id' => Subject::create(['name' => ['ar' => 'مادة', 'en' => 'Subject']])->id,
        'price' => 0,
        'start_date' => now()->subWeek()->toDateString(),
        'end_date' => now()->addMonths(2)->toDateString(),
    ]);

    SectionTime::create([
        'section_id' => $section->id,
        'room_id' => $room->id,
        'day' => 'friday',
        'start_time' => '10:00',
        'end_time' => '11:00',
    ]);

    expect(SectionTime::where('section_id', $section->id)->count())->toBe(1);
});

it('refuses a slot on a weekday the booking never runs on', function () {
    $admin = bookingAdmin();
    $room = Room::create(['number' => 'B-'.uniqid()]);
    $saturday = nextWeekday('saturday');

    Livewire::actingAs($admin)
        ->test(CreateRoomBooking::class)
        ->fillForm([
            'title' => 'حجز ليوم واحد',
            'room_id' => $room->id,
            'status' => RoomBooking::STATUS_CONFIRMED,
            'start_date' => $saturday->toDateString(),
            'end_date' => $saturday->toDateString(),
            'price' => 0,
            'times' => [
                ['day' => 'tuesday', 'start_time' => '10:00', 'end_time' => '12:00'],
            ],
        ])
        ->call('create')
        ->assertHasFormErrors(['times']);

    expect(RoomBooking::count())->toBe(0);
});

// -------------------------------------------------------------- payments

it('tracks a booking price against its instalments', function () {
    $room = Room::create(['number' => 'B-'.uniqid()]);
    $booking = makeBooking($room, 'sunday', '09:00', '11:00', ['price' => 1000]);
    $cash = PaymentType::create(['name' => ['ar' => 'نقداً', 'en' => 'Cash']]);

    expect($booking->paymentStatus())->toBe('unpaid')
        ->and($booking->remainingAmount())->toBe(1000.0);

    Storage::fake('public');

    CollectBookingPaymentAction::collect($booking, [
        'amount' => 400,
        'payment_type_id' => $cash->id,
        'paid_at' => now(),
        'receipt' => receiptFixture(),
        'note' => 'عربون',
    ]);

    $booking->refresh();
    $instalment = $booking->payments()->first();

    expect($booking->paidAmount())->toBe(400.0)
        ->and($booking->remainingAmount())->toBe(600.0)
        ->and($booking->paymentStatus())->toBe('partial')
        // The receipt lives in the media library, attached to the instalment
        // it belongs to rather than to the booking as a whole.
        ->and($instalment->getMedia(ReceiptAttachment::COLLECTION))->toHaveCount(1)
        ->and($instalment->receiptUrl())->not->toBeNull()
        ->and($instalment->payment_type_id)->toBe($cash->id);

    CollectBookingPaymentAction::collect($booking, ['amount' => 600, 'paid_at' => now()]);

    $booking->refresh();

    expect($booking->remainingAmount())->toBe(0.0)
        ->and($booking->paymentStatus())->toBe('paid');
});

it('never reports a negative remaining on an overpaid booking', function () {
    $room = Room::create(['number' => 'B-'.uniqid()]);
    $booking = makeBooking($room, 'sunday', '09:00', '11:00', ['price' => 100]);

    CollectBookingPaymentAction::collect($booking, ['amount' => 150, 'paid_at' => now()]);

    expect($booking->refresh()->remainingAmount())->toBe(0.0);
});

// -------------------------------------------------------------- calendar

it('draws a booking on the general calendar alongside the lessons', function () {
    $admin = bookingAdmin();
    $room = Room::create(['number' => 'B-'.uniqid()]);
    $monday = nextWeekday('monday');

    $booking = makeBooking($room, 'monday', '18:00', '20:00');

    $page = Livewire::actingAs($admin)->test(SectionsCalendar::class);

    $page->assertSee($booking->title);

    $events = $page->instance()->eventsFor($monday);

    expect($events)->toHaveCount(1)
        ->and(SectionsCalendar::isBooking($events->first()))->toBeTrue()
        ->and(SectionsCalendar::eventRoom($events->first())->id)->toBe($room->id);
});

it('keeps a booking off the days its date range does not cover', function () {
    $admin = bookingAdmin();
    $room = Room::create(['number' => 'B-'.uniqid()]);

    makeBooking($room, 'monday', '18:00', '20:00', [
        'start_date' => now()->subMonths(3)->toDateString(),
        'end_date' => now()->subMonths(2)->toDateString(),
    ]);

    $page = Livewire::actingAs($admin)->test(SectionsCalendar::class);

    expect($page->instance()->eventsFor(nextWeekday('monday')))->toHaveCount(0);
});

it('hides bookings when the calendar is filtered to one course', function () {
    $admin = bookingAdmin();
    $room = Room::create(['number' => 'B-'.uniqid()]);
    $subject = Subject::create(['name' => ['ar' => 'مادة', 'en' => 'Subject']]);

    makeBooking($room, 'monday', '18:00', '20:00');

    $page = Livewire::actingAs($admin)
        ->test(SectionsCalendar::class)
        ->set('filters.subject_id', $subject->id);

    expect($page->instance()->eventsFor(nextWeekday('monday')))->toHaveCount(0);
});

it('keeps a booking visible when filtering by its own room', function () {
    $admin = bookingAdmin();
    $room = Room::create(['number' => 'B-'.uniqid()]);
    $other = Room::create(['number' => 'B-'.uniqid()]);

    makeBooking($room, 'monday', '18:00', '20:00');

    $page = Livewire::actingAs($admin)->test(SectionsCalendar::class);
    $monday = nextWeekday('monday');

    expect($page->set('filters.room_id', $room->id)->instance()->eventsFor($monday))->toHaveCount(1)
        ->and($page->set('filters.room_id', $other->id)->instance()->eventsFor($monday))->toHaveCount(0);
});

// --------------------------------------------------------------- reports

it('reports booking income by the day each instalment arrived', function () {
    $admin = bookingAdmin();
    $room = Room::create(['number' => 'B-'.uniqid()]);
    $booking = makeBooking($room, 'sunday', '09:00', '11:00', ['price' => 1000]);

    CollectBookingPaymentAction::collect($booking, ['amount' => 400, 'paid_at' => now()]);
    // Banked before the window opened: outside the period, so outside the total.
    CollectBookingPaymentAction::collect($booking, [
        'amount' => 250,
        'paid_at' => now()->subMonthNoOverflow()->startOfMonth(),
    ]);

    $stats = Livewire::actingAs($admin)->test(Reports::class)->instance()->getBookingStatsProperty();

    expect($stats['collected'])->toBe(400.0)
        ->and($stats['contracted'])->toBe(1000.0)
        ->and($stats['outstanding'])->toBe(350.0)
        ->and($stats['count'])->toBe(1);
});

it('leaves cancelled bookings out of the reported income', function () {
    $admin = bookingAdmin();
    $room = Room::create(['number' => 'B-'.uniqid()]);

    makeBooking($room, 'sunday', '09:00', '11:00', [
        'price' => 800,
        'status' => RoomBooking::STATUS_CANCELLED,
    ]);

    $stats = Livewire::actingAs($admin)->test(Reports::class)->instance()->getBookingStatsProperty();

    expect($stats['count'])->toBe(0)
        ->and($stats['contracted'])->toBe(0.0)
        ->and($stats['outstanding'])->toBe(0.0);
});
