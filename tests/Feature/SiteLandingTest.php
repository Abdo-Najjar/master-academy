<?php

use App\Models\Branch;
use App\Models\City;
use App\Models\Governorate;
use App\Models\Program;
use App\Models\SiteMedia;
use App\Models\Testimonial;
use App\Models\Trainer;
use Livewire\Volt\Volt;

function makeProgram(array $attributes = []): Program
{
    return Program::create(array_merge([
        'title' => ['ar' => 'دبلوم تجريبي', 'en' => 'Sample Diploma'],
        'description' => ['ar' => 'وصف', 'en' => 'Description'],
        'category' => 'technical',
        'icon' => '☀',
        'is_active' => true,
    ], $attributes));
}

function makeBranch(array $attributes = []): Branch
{
    $governorate = Governorate::create(['name' => ['ar' => 'غزة', 'en' => 'Gaza']]);
    $city = City::create(['name' => ['ar' => 'الرمال', 'en' => 'Al-Rimal'], 'governorate_id' => $governorate->id]);

    return Branch::create(array_merge([
        'name' => ['ar' => 'فرع غزة', 'en' => 'Gaza Branch'],
        'address' => ['ar' => 'الرمال — سيزون مول', 'en' => 'Al-Rimal — Season Mall'],
        'governorate_id' => $governorate->id,
        'city_id' => $city->id,
        'show_on_site' => true,
    ], $attributes));
}

it('renders the landing page', function () {
    $this->get('/')->assertOk();
});

it('keeps the portal reachable at its own path', function () {
    $this->get('/portal')->assertOk();
});

it('lists only active programs', function () {
    $live = makeProgram(['title' => ['ar' => 'برنامج منشور', 'en' => 'Published Program']]);
    $hidden = makeProgram(['title' => ['ar' => 'برنامج مخفي', 'en' => 'Hidden Program'], 'is_active' => false]);

    Volt::test('site.landing')
        ->assertSee($live->title)
        ->assertDontSee($hidden->title);
});

it('filters programs by category', function () {
    $technical = makeProgram(['title' => ['ar' => 'برنامج تقني', 'en' => 'Technical Program'], 'category' => 'technical']);
    $creative = makeProgram(['title' => ['ar' => 'برنامج إبداعي', 'en' => 'Creative Program'], 'category' => 'creative']);

    Volt::test('site.landing')
        ->call('filterBy', 'creative')
        ->assertSet('category', 'creative')
        ->assertSee($creative->title)
        ->assertDontSee($technical->title);
});

it('only offers filter categories that have a visible program', function () {
    makeProgram(['category' => 'technical']);

    $categories = array_keys(Volt::test('site.landing')->instance()->categories);

    expect($categories)->toBe(['all', 'technical']);
});

it('shows only trainers opted in to the site', function () {
    $shown = Trainer::create([
        'name' => ['ar' => 'مدرب ظاهر', 'en' => 'Visible Trainer'],
        'username' => 'visible-trainer',
        'password' => 'password',
        'is_active' => true,
        'show_on_site' => true,
        'specialty' => ['ar' => 'التصوير', 'en' => 'Photography'],
    ]);
    $hidden = Trainer::create([
        'name' => ['ar' => 'مدرب مخفي', 'en' => 'Hidden Trainer'],
        'username' => 'hidden-trainer',
        'password' => 'password',
        'is_active' => true,
        'show_on_site' => false,
    ]);

    Volt::test('site.landing')
        ->assertSee($shown->name)
        ->assertDontSee($hidden->name);
});

it('shows only branches opted in to the site', function () {
    $shown = makeBranch();
    $hidden = makeBranch(['name' => ['ar' => 'فرع مخفي', 'en' => 'Hidden Branch'], 'show_on_site' => false]);

    Volt::test('site.landing')
        ->assertSee($shown->address)
        ->assertDontSee($hidden->name);
});

it('shows active testimonials and gallery items', function () {
    $testimonial = Testimonial::create([
        'name' => ['ar' => 'خريج راضٍ', 'en' => 'Happy Graduate'],
        'quote' => ['ar' => 'تجربة ممتازة جدًا', 'en' => 'A really great experience'],
        'is_active' => true,
    ]);
    $media = SiteMedia::create([
        'title' => ['ar' => 'جلسة تصوير', 'en' => 'Photo session'],
        'type' => 'image',
        'url' => 'https://example.com/photo.jpg',
        'is_active' => true,
    ]);

    Volt::test('site.landing')
        ->assertSee($testimonial->quote)
        ->assertSee($media->title);
});
