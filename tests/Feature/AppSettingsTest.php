<?php

use App\Settings\AppSettings;
use App\Support\AppBranding;

it('loads default settings from the migration seed', function () {
    $settings = app(AppSettings::class);

    expect($settings->app_name)->toBeString()->not->toBeEmpty();
    expect($settings->primary_color)->toMatch('/^#[0-9a-f]{6}$/i');
    expect($settings->secondary_color)->toMatch('/^#[0-9a-f]{6}$/i');
});

it('persists changes to settings', function () {
    $settings = app(AppSettings::class);
    $settings->app_name = 'Test School';
    $settings->primary_color = '#123456';
    $settings->save();

    // Re-resolve to force a fresh read
    app()->forgetInstance(AppSettings::class);
    $fresh = app(AppSettings::class);

    expect($fresh->app_name)->toBe('Test School');
    expect($fresh->primary_color)->toBe('#123456');
});

it('reads app name through AppBranding helper', function () {
    expect(AppBranding::appName())->toBeString();
});

it('builds a Filament colors map from the settings', function () {
    $colors = AppBranding::panelColors();

    expect($colors)->toHaveKeys(['primary', 'secondary']);
    expect($colors['primary'])->toBeArray()->not->toBeEmpty();
});
