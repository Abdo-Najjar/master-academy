<?php

use App\Models\Branch;
use App\Models\City;
use App\Models\Governorate;
use App\Models\JoinApplication;
use App\Models\Program;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Livewire\Volt\Volt;

beforeEach(function () {
    RateLimiter::clear('join-application:127.0.0.1');

    $governorate = Governorate::create(['name' => ['ar' => 'غزة', 'en' => 'Gaza']]);
    $city = City::create(['name' => ['ar' => 'الرمال', 'en' => 'Al-Rimal'], 'governorate_id' => $governorate->id]);

    $this->branch = Branch::create([
        'name' => ['ar' => 'فرع غزة', 'en' => 'Gaza Branch'],
        'governorate_id' => $governorate->id,
        'city_id' => $city->id,
        'show_on_site' => true,
    ]);

    $this->program = Program::create([
        'title' => ['ar' => 'دبلوم الطاقة الشمسية', 'en' => 'Solar Diploma'],
        'category' => 'technical',
        'is_active' => true,
    ]);
});

function fillJoinForm($component, array $overrides = [])
{
    $data = array_merge([
        'full_name' => 'عبد الرحمن النجار',
        'phone' => '0599123456',
        'age' => 22,
        'gender' => 'male',
        'contact_preference' => 'whatsapp',
        'notes' => 'أرجو التواصل مساءً',
    ], $overrides);

    foreach ($data as $key => $value) {
        $component->set($key, $value);
    }

    return $component;
}

it('renders the join page', function () {
    $this->get('/join')->assertOk();
});

it('stores a join request and shows the reference', function () {
    $component = Volt::test('site.join');

    fillJoinForm($component, [
        'program' => (string) $this->program->id,
        'branch_id' => $this->branch->id,
    ])->call('submit');

    $application = JoinApplication::sole();

    expect($application->full_name)->toBe('عبد الرحمن النجار')
        ->and($application->program_id)->toBe($this->program->id)
        ->and($application->branch_id)->toBe($this->branch->id)
        ->and($application->status)->toBe('new')
        ->and($application->reference)->toHaveLength(8);

    $component->assertSet('reference', $application->reference)
        ->assertSee($application->reference);
});

it('requires the applicant details', function () {
    Volt::test('site.join')
        ->call('submit')
        ->assertHasErrors(['full_name', 'phone', 'age', 'gender', 'program', 'branch_id']);

    expect(JoinApplication::count())->toBe(0);
});

it('requires a typed program name when "other" is chosen', function () {
    $component = Volt::test('site.join');

    fillJoinForm($component, [
        'program' => 'other',
        'branch_id' => $this->branch->id,
    ])->call('submit')->assertHasErrors(['program_name']);

    expect(JoinApplication::count())->toBe(0);
});

it('stores a free-text program when "other" is chosen', function () {
    $component = Volt::test('site.join');

    fillJoinForm($component, [
        'program' => 'other',
        'program_name' => 'دورة اللغة التركية',
        'branch_id' => $this->branch->id,
    ])->call('submit');

    $application = JoinApplication::sole();

    expect($application->program_id)->toBeNull()
        ->and($application->program_name)->toBe('دورة اللغة التركية')
        ->and($application->requested_program)->toBe('دورة اللغة التركية');
});

// Note: these go through the Livewire facade, not Volt. `Volt::withQueryParams()`
// does not carry the params into the testable that `Volt::test()` builds, so the
// component would mount with empty URL properties.
it('preselects the program passed in the query string', function () {
    Livewire::withQueryParams(['program' => (string) $this->program->id])
        ->test('site.join')
        ->assertSet('program', (string) $this->program->id);
});

it('drops a query string program that is not published', function () {
    $this->program->update(['is_active' => false]);

    Livewire::withQueryParams(['program' => (string) $this->program->id])
        ->test('site.join')
        ->assertSet('program', '');
});

it('silently discards submissions that fill the honeypot', function () {
    Volt::test('site.join')
        ->set('website', 'https://spam.example')
        ->call('submit')
        ->assertSet('reference', fn (?string $reference): bool => $reference !== null);

    expect(JoinApplication::count())->toBe(0);
});

it('rate limits repeated submissions from the same address', function () {
    foreach (range(1, 5) as $attempt) {
        $component = Volt::test('site.join');

        fillJoinForm($component, [
            'program' => (string) $this->program->id,
            'branch_id' => $this->branch->id,
        ])->call('submit');
    }

    expect(JoinApplication::count())->toBe(5);

    $blocked = Volt::test('site.join');

    fillJoinForm($blocked, [
        'program' => (string) $this->program->id,
        'branch_id' => $this->branch->id,
    ])->call('submit')->assertSet('reference', null);

    expect(JoinApplication::count())->toBe(5)
        ->and($blocked->get('formError'))->not->toBeNull();
});
