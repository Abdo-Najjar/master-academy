<?php

use App\Filament\Admin\Pages\Reports;
use App\Filament\Admin\Pages\SectionsCalendar;
use App\Filament\Admin\Resources\Expenses\Pages\ManageExpenses;
use App\Filament\Admin\Resources\RoomBookings\Actions\CollectBookingPaymentAction;
use App\Filament\Admin\Resources\RoomBookings\Pages\CreateRoomBooking;
use App\Filament\Admin\Resources\RoomBookings\Pages\ListRoomBookings;
use App\Filament\Admin\Resources\Sections\Pages\CreateSection;
use App\Models\Branch;
use App\Models\City;
use App\Models\Expense;
use App\Models\ExpenseType;
use App\Models\Governorate;
use App\Models\PaymentType;
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
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * One centre, one term, one afternoon — driven through the real screens with
 * the data a real desk would type.
 *
 * The other files check each rule on its own. This one is the walk-through: an
 * English course already meets in room 1 on Saturday afternoons, an outside
 * body wants the same hall, the rent falls due, and the reports have to add all
 * of it up. Every step goes through the page the operator would actually be
 * looking at, because a rule that holds in a unit test and not in the form is
 * a rule the centre does not have.
 */
beforeEach(function () {
    $gates = PermissionCatalog::allGates();

    foreach ($gates as $gate) {
        Permission::firstOrCreate(['name' => $gate, 'guard_name' => 'web']);
    }

    $this->admin = User::create([
        'name' => 'مدير المركز',
        'email' => 'scenario-'.uniqid().'@ma.test',
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
        'is_active' => true,
    ]);

    $role = Role::create(['name' => 'scenario-'.uniqid(), 'guard_name' => 'web']);
    $role->syncPermissions($gates);
    $this->admin->assignRole($role);

    $governorate = Governorate::create(['name' => ['ar' => 'رام الله والبيرة', 'en' => 'Ramallah']]);
    $city = City::create([
        'governorate_id' => $governorate->id,
        'name' => ['ar' => 'رام الله', 'en' => 'Ramallah'],
    ]);

    $this->branch = Branch::create([
        'name' => ['ar' => 'الفرع الرئيسي', 'en' => 'Main Branch'],
        'governorate_id' => $governorate->id,
        'city_id' => $city->id,
    ]);
    // Rooms stand in a branch now, and the booking form only offers the halls
    // of the site it is being filed under.
    $this->hall = Room::create([
        'branch_id' => $this->branch->id,
        'number' => '1',
        'capacity' => 30,
        'description' => 'القاعة الكبرى',
    ]);

    $this->smallRoom = Room::create([
        'branch_id' => $this->branch->id,
        'number' => '2',
        'capacity' => 12,
    ]);

    $this->cash = PaymentType::create(['name' => ['ar' => 'نقداً', 'en' => 'Cash']]);
    $this->transfer = PaymentType::create(['name' => ['ar' => 'تحويل بنكي', 'en' => 'Bank Transfer']]);

    $this->trainer = Trainer::create([
        'name' => ['ar' => 'أ. سامي', 'en' => 'Sami'],
        'username' => 'sami_'.uniqid(),
        'password' => 'password',
        'default_rate' => 40,
    ]);

    $this->subject = Subject::create(['name' => ['ar' => 'اللغة الإنجليزية', 'en' => 'English']]);

    // The course that already owns the hall: Saturdays, 16:00–18:00, room 1,
    // running from last month to three months out.
    $this->section = Section::create([
        'name' => 'إنجليزي - مستوى أول',
        'subject_id' => $this->subject->id,
        'branch_id' => $this->branch->id,
        'trainer_id' => $this->trainer->id,
        'price' => 400,
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addMonths(3)->toDateString(),
    ]);

    SectionTime::create([
        'section_id' => $this->section->id,
        'room_id' => $this->hall->id,
        'day' => 'saturday',
        'start_time' => '16:00',
        'end_time' => '18:00',
    ]);
});

/**
 * The booking form as the desk would fill it in, with one weekly slot.
 *
 * The branch is named on purpose: it is required wherever branches exist, and
 * the room list narrows to that site's halls once it is chosen.
 */
function bookingFormData(array $overrides = [], array $times = []): array
{
    return array_merge([
        'branch_id' => test()->branch->id,
        'title' => 'ورشة تدريب موظفي البلدية',
        'client_name' => 'بلدية المدينة',
        'client_phone' => '0599123456',
        'status' => RoomBooking::STATUS_CONFIRMED,
        'start_date' => now()->subWeek()->toDateString(),
        'end_date' => now()->addMonths(2)->toDateString(),
        'price' => 1800,
        'note' => 'تشمل استخدام جهاز العرض',
        'times' => $times ?: [
            ['day' => 'saturday', 'start_time' => '18:30', 'end_time' => '21:00'],
        ],
    ], $overrides);
}

/** The next Saturday, which is when both the lesson and the booking land. */
function scenarioSaturday(): Carbon
{
    $date = now()->startOfDay();

    while ($date->dayOfWeek !== Carbon::SATURDAY) {
        $date->addDay();
    }

    return $date;
}

it('refuses a hall booking that lands on the English lesson, and takes the later slot', function () {
    // 16:00–18:00 on Saturday is the lesson. 17:00–19:00 walks into it.
    Livewire::actingAs($this->admin)
        ->test(CreateRoomBooking::class)
        ->fillForm(bookingFormData(['room_id' => $this->hall->id], [
            ['day' => 'saturday', 'start_time' => '17:00', 'end_time' => '19:00'],
        ]))
        ->call('create')
        ->assertHasFormErrors(['times']);

    expect(RoomBooking::count())->toBe(0);

    // Moved to after the lesson finishes, the same hall is free.
    Livewire::actingAs($this->admin)
        ->test(CreateRoomBooking::class)
        ->fillForm(bookingFormData(['room_id' => $this->hall->id]))
        ->call('create')
        ->assertHasNoFormErrors();

    $booking = RoomBooking::query()->latest('id')->first();

    expect($booking->title)->toBe('ورشة تدريب موظفي البلدية')
        ->and($booking->room_id)->toBe($this->hall->id)
        ->and((float) $booking->price)->toBe(1800.0)
        ->and($booking->times)->toHaveCount(1)
        ->and($booking->timesLabel())->toContain('18:30');
});

it('refuses a new course that wants the hall the workshop has booked', function () {
    Livewire::actingAs($this->admin)
        ->test(CreateRoomBooking::class)
        ->fillForm(bookingFormData(['room_id' => $this->hall->id]))
        ->call('create')
        ->assertHasNoFormErrors();

    // A second course, same hall, Saturday 19:00 — inside the workshop's slot.
    // The form has to refuse it before the section row is written, or the
    // centre ends up with a course that has nowhere to meet.
    Livewire::actingAs($this->admin)
        ->test(CreateSection::class)
        ->fillForm([
            'name' => 'إنجليزي - مستوى ثاني',
            'subject_id' => $this->subject->id,
            'branch_id' => $this->branch->id,
            'price' => 400,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(2)->toDateString(),
            'times' => [
                ['day' => 'saturday', 'start_time' => '19:00', 'end_time' => '20:30', 'room_id' => $this->hall->id],
            ],
        ])
        ->call('create')
        ->assertHasFormErrors(['times']);

    expect(Section::where('name', 'إنجليزي - مستوى ثاني')->count())->toBe(0);

    // The small room at the same hour is nobody's, so it goes through.
    Livewire::actingAs($this->admin)
        ->test(CreateSection::class)
        ->fillForm([
            'name' => 'إنجليزي - مستوى ثاني',
            'subject_id' => $this->subject->id,
            'branch_id' => $this->branch->id,
            'price' => 400,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(2)->toDateString(),
            'times' => [
                ['day' => 'saturday', 'start_time' => '19:00', 'end_time' => '20:30', 'room_id' => $this->smallRoom->id],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Section::where('name', 'إنجليزي - مستوى ثاني')->count())->toBe(1);
});

it('holds the hall against a second booking but frees it once cancelled', function () {
    Livewire::actingAs($this->admin)
        ->test(CreateRoomBooking::class)
        ->fillForm(bookingFormData(['room_id' => $this->hall->id]))
        ->call('create');

    $first = RoomBooking::query()->latest('id')->first();

    // Another body wants the same hall on the same evening.
    Livewire::actingAs($this->admin)
        ->test(CreateRoomBooking::class)
        ->fillForm(bookingFormData([
            'title' => 'حفل تخريج',
            'client_name' => 'جمعية خيرية',
            'room_id' => $this->hall->id,
        ], [
            ['day' => 'saturday', 'start_time' => '19:00', 'end_time' => '22:00'],
        ]))
        ->call('create')
        ->assertHasFormErrors(['times']);

    expect(RoomBooking::count())->toBe(1);

    // The first booking falls through, so the hall is up for grabs again.
    $first->update(['status' => RoomBooking::STATUS_CANCELLED]);

    Livewire::actingAs($this->admin)
        ->test(CreateRoomBooking::class)
        ->fillForm(bookingFormData([
            'title' => 'حفل تخريج',
            'client_name' => 'جمعية خيرية',
            'room_id' => $this->hall->id,
        ], [
            ['day' => 'saturday', 'start_time' => '19:00', 'end_time' => '22:00'],
        ]))
        ->call('create')
        ->assertHasNoFormErrors();

    expect(RoomBooking::count())->toBe(2);
});

it('collects the booking in instalments, each with its own method and receipt', function () {
    Storage::fake('public');

    Livewire::actingAs($this->admin)
        ->test(CreateRoomBooking::class)
        ->fillForm(bookingFormData(['room_id' => $this->hall->id]))
        ->call('create');

    $booking = RoomBooking::query()->latest('id')->first();

    expect($booking->paymentStatus())->toBe('unpaid')
        ->and($booking->remainingAmount())->toBe(1800.0);

    // A deposit in cash on the day the hall was held…
    CollectBookingPaymentAction::collect($booking, [
        'amount' => 500,
        'payment_type_id' => $this->cash->id,
        'paid_at' => now()->subDays(3),
        'receipt' => receiptFixture('deposit.jpg'),
        'note' => 'عربون حجز',
    ]);

    $booking->refresh();

    expect($booking->paidAmount())->toBe(500.0)
        ->and($booking->remainingAmount())->toBe(1300.0)
        ->and($booking->paymentStatus())->toBe('partial');

    // …then the balance by transfer.
    CollectBookingPaymentAction::collect($booking, [
        'amount' => 1300,
        'payment_type_id' => $this->transfer->id,
        'paid_at' => now(),
        'receipt' => receiptFixture('balance.pdf'),
        'note' => 'تسديد المتبقي',
    ]);

    $booking->refresh();
    $instalments = $booking->payments()->orderBy('paid_at')->get();

    expect($booking->paidAmount())->toBe(1800.0)
        ->and($booking->remainingAmount())->toBe(0.0)
        ->and($booking->paymentStatus())->toBe('paid')
        ->and($instalments)->toHaveCount(2)
        ->and($instalments[0]->payment_type_id)->toBe($this->cash->id)
        ->and($instalments[1]->payment_type_id)->toBe($this->transfer->id)
        // Every instalment keeps its own voucher.
        ->and($instalments[0]->receiptUrl())->not->toBeNull()
        ->and($instalments[1]->receiptUrl())->not->toBeNull()
        ->and($instalments[0]->getFirstMedia(ReceiptAttachment::COLLECTION)->file_name)->toContain('deposit');

    // The list screen reads the same numbers back.
    Livewire::actingAs($this->admin)
        ->test(ListRoomBookings::class)
        ->assertCanSeeTableRecords([$booking]);
});

it('shows the lesson and the workshop in the same Saturday cell, in time order', function () {
    Livewire::actingAs($this->admin)
        ->test(CreateRoomBooking::class)
        ->fillForm(bookingFormData(['room_id' => $this->hall->id]))
        ->call('create');

    $page = Livewire::actingAs($this->admin)->test(SectionsCalendar::class);

    $page->assertSee('إنجليزي - مستوى أول')
        ->assertSee('ورشة تدريب موظفي البلدية');

    $events = $page->instance()->eventsFor(scenarioSaturday());

    expect($events)->toHaveCount(2)
        // 16:00 lesson before the 18:30 workshop.
        ->and(SectionsCalendar::isBooking($events[0]))->toBeFalse()
        ->and(SectionsCalendar::isBooking($events[1]))->toBeTrue()
        ->and(SectionsCalendar::eventRoom($events[1])->id)->toBe($this->hall->id);
});

it('carries the workshop into the printed timetable and the spreadsheet', function () {
    Livewire::actingAs($this->admin)
        ->test(CreateRoomBooking::class)
        ->fillForm(bookingFormData(['room_id' => $this->hall->id]))
        ->call('create');

    $calendar = Livewire::actingAs($this->admin)->test(SectionsCalendar::class)->instance();

    // The wall timetable: room 1 gets a band, and its Saturday box holds the
    // lesson and the workshop, the latter badged as a booking.
    $weeks = $calendar->weeklyGrid();
    $cells = collect($weeks)
        ->flatMap(fn (array $week): array => $week['rooms'])
        ->flatMap(fn (array $room): array => array_merge(...array_values($room['cells'])));

    expect($cells->pluck('section'))->toContain('إنجليزي - مستوى أول')
        ->and($cells->pluck('section'))->toContain('ورشة تدريب موظفي البلدية')
        ->and($cells->firstWhere('section', 'ورشة تدريب موظفي البلدية')['subject'])->toBe(__('Room Booking'));

    // And both exports stream rather than blowing up on the new row type.
    expect($calendar->exportPeriod()->getStatusCode())->toBe(200)
        ->and($calendar->exportWeeklyPdf()?->getStatusCode())->toBe(200);
});

it('adds the rent and the booking income up on the reports page', function () {
    Storage::fake('public');

    Livewire::actingAs($this->admin)
        ->test(CreateRoomBooking::class)
        ->fillForm(bookingFormData(['room_id' => $this->hall->id]))
        ->call('create');

    $booking = RoomBooking::query()->latest('id')->first();

    CollectBookingPaymentAction::collect($booking, [
        'amount' => 500,
        'payment_type_id' => $this->cash->id,
        'paid_at' => now(),
    ]);

    $rent = ExpenseType::create(['name' => ['ar' => 'إيجار مكان', 'en' => 'Venue Rent']]);
    $bills = ExpenseType::create(['name' => ['ar' => 'فواتير كهرباء ومياه', 'en' => 'Utilities']]);

    // The rent goes in through the screen the accountant would use.
    Livewire::actingAs($this->admin)
        ->test(ManageExpenses::class)
        ->callAction('create', data: [
            'expense_type_id' => $rent->id,
            'payment_type_id' => $this->transfer->id,
            'branch_id' => $this->branch->id,
            'amount' => 3000,
            'spent_at' => now()->toDateString(),
            'payee' => 'أبو أحمد - صاحب البناية',
            'reference' => 'INV-2026-09',
            'note' => 'إيجار الشهر',
        ])
        ->assertHasNoActionErrors();

    Expense::create([
        'expense_type_id' => $bills->id,
        'payment_type_id' => $this->cash->id,
        'amount' => 420,
        'spent_at' => now()->toDateString(),
        'payee' => 'شركة الكهرباء',
    ]);

    $reports = Livewire::actingAs($this->admin)->test(Reports::class)->instance();

    expect($reports->getExpenseStatsProperty()['total'])->toBe(3420.0)
        ->and($reports->getExpenseStatsProperty()['count'])->toBe(2)
        ->and($reports->getBookingStatsProperty()['collected'])->toBe(500.0)
        ->and($reports->getBookingStatsProperty()['outstanding'])->toBe(1300.0)
        // No student money in this scenario, so the result is bookings minus
        // what went out: 500 − 3420.
        ->and($reports->getNetResultProperty())->toBe(-2920.0);

    $breakdown = $reports->getExpenseBreakdownProperty();

    expect($breakdown[0]['name'])->toBe('إيجار مكان')
        ->and($breakdown[0]['total'])->toBe(3000.0)
        ->and($breakdown[1]['name'])->toBe('فواتير كهرباء ومياه');

    Livewire::actingAs($this->admin)
        ->test(Reports::class)
        ->assertSee('إيجار مكان')
        ->assertSee('فواتير كهرباء ومياه');
});

it('adds up what was actually banked, by how it was paid', function () {
    Livewire::actingAs($this->admin)
        ->test(CreateRoomBooking::class)
        ->fillForm(bookingFormData(['room_id' => $this->hall->id]))
        ->call('create');

    $booking = RoomBooking::query()->latest('id')->first();

    CollectBookingPaymentAction::collect($booking, [
        'amount' => 500,
        'payment_type_id' => $this->cash->id,
        'paid_at' => now(),
    ]);

    CollectBookingPaymentAction::collect($booking, [
        'amount' => 1300,
        'payment_type_id' => $this->transfer->id,
        'paid_at' => now(),
    ]);

    // A student pays their course fee in cash at the desk.
    $student = Student::create([
        'name' => ['ar' => 'ليان أحمد', 'en' => 'Layan'],
        'username' => 'layan_'.uniqid(),
        'password' => 'password',
        'status' => 'active',
    ]);

    $student->depositFloat(400, [
        'description' => 'رسوم الدورة',
        'payment_type_id' => $this->cash->id,
    ]);

    // Last month's money is last month's, whichever drawer it went in.
    $old = $student->depositFloat(999, ['payment_type_id' => $this->cash->id]);
    $old->forceFill(['created_at' => now()->subMonthNoOverflow()->startOfMonth()])->saveQuietly();

    $collections = Livewire::actingAs($this->admin)->test(Reports::class)->instance()->getCollectionsProperty();

    // 500 + 400 cash, 1300 transfer — the 999 is outside the window.
    expect($collections['total'])->toBe(2200.0)
        ->and($collections['count'])->toBe(3)
        ->and($collections['by_type'][0]['name'])->toBe('تحويل بنكي')
        ->and($collections['by_type'][0]['total'])->toBe(1300.0)
        ->and($collections['by_type'][1]['name'])->toBe('نقداً')
        ->and($collections['by_type'][1]['total'])->toBe(900.0)
        ->and($collections['by_type'][1]['count'])->toBe(2);

    Livewire::actingAs($this->admin)
        ->test(Reports::class)
        ->assertSee(__('Payments Received by Method'));
});

it('drops the centre-wide money panels when the report is narrowed to one trainer', function () {
    Expense::create([
        'expense_type_id' => ExpenseType::create(['name' => ['ar' => 'إيجار مكان', 'en' => 'Rent']])->id,
        'amount' => 3000,
        'spent_at' => now()->toDateString(),
    ]);

    // Rent is not any one trainer's, so the panel steps aside rather than
    // showing a centre-wide total under their name.
    Livewire::actingAs($this->admin)
        ->test(Reports::class)
        ->assertSee(__('Expenses by Type'))
        ->set('filters.trainer_id', $this->trainer->id)
        ->assertSuccessful()
        ->assertDontSee(__('Expenses by Type'))
        ->assertDontSee(__('Payments Received by Method'));
});

it('lets a finished booking go without holding the hall forever', function () {
    // A workshop that ran through the spring and is long over.
    $past = RoomBooking::create([
        'room_id' => $this->hall->id,
        'title' => 'ورشة انتهت',
        'start_date' => now()->subMonths(5)->toDateString(),
        'end_date' => now()->subMonths(4)->toDateString(),
        'price' => 0,
    ]);

    RoomBookingTime::create([
        'room_booking_id' => $past->id,
        'day' => 'saturday',
        'start_time' => '18:30',
        'end_time' => '21:00',
    ]);

    // The same evening this term is nobody's, so it books cleanly.
    Livewire::actingAs($this->admin)
        ->test(CreateRoomBooking::class)
        ->fillForm(bookingFormData(['room_id' => $this->hall->id]))
        ->call('create')
        ->assertHasNoFormErrors();

    expect(RoomBooking::active()->count())->toBe(2);
});

it('will not let the observer be bypassed by writing the slot directly', function () {
    Livewire::actingAs($this->admin)
        ->test(CreateRoomBooking::class)
        ->fillForm(bookingFormData(['room_id' => $this->hall->id]))
        ->call('create');

    $booking = RoomBooking::query()->latest('id')->first();

    // Whatever screen (or import, or console command) writes the row, the same
    // rule applies — this is the backstop under the form validation.
    expect(fn () => RoomBookingTime::create([
        'room_booking_id' => $booking->id,
        'day' => 'saturday',
        'start_time' => '16:30',
        'end_time' => '17:30',
    ]))->toThrow(ValidationException::class);

    expect(fn () => SectionTime::create([
        'section_id' => $this->section->id,
        'room_id' => $this->hall->id,
        'day' => 'saturday',
        'start_time' => '19:00',
        'end_time' => '20:00',
    ]))->toThrow(ValidationException::class);
});
