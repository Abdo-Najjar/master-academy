<?php

use App\Filament\Admin\Widgets\AttendanceBreakdownWidget;
use App\Filament\Admin\Widgets\DuePaymentsWidget;
use App\Filament\Admin\Widgets\OverviewStatsWidget;
use App\Filament\Admin\Widgets\RegistrationsChartWidget;
use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function dashboardUser(array $gates): User
{
    // User #1 is the super admin and bypasses Gate::before — burn that id.
    if (! User::query()->whereKey(1)->exists()) {
        User::create([
            'name' => 'Super Admin',
            'email' => 'super@ma.test',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
    }

    $user = User::create([
        'name' => 'Limited',
        'email' => 'limited-'.uniqid().'@ma.test',
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
        'is_active' => true,
    ]);

    foreach ($gates as $gate) {
        Permission::firstOrCreate(['name' => $gate, 'guard_name' => 'web']);
    }

    $role = Role::create(['name' => 'role-'.uniqid(), 'guard_name' => 'web']);
    $role->syncPermissions($gates);
    $user->assignRole($role);

    return $user;
}

/**
 * The labels the stats widget would actually render.
 *
 * Asserted straight off the widget rather than by scraping the dashboard HTML:
 * Filament v4 widgets load lazily, so the first paint only contains a
 * placeholder and the stats never appear in that response.
 *
 * @return list<string>
 */
function statLabels(): array
{
    $method = new ReflectionMethod(OverviewStatsWidget::class, 'getStats');
    $method->setAccessible(true);

    return array_map(
        fn ($stat): string => (string) $stat->getLabel(),
        $method->invoke(new OverviewStatsWidget),
    );
}

test('a students-only user does not see financial or complaint stats on the dashboard', function () {
    $this->actingAs(dashboardUser(['student.index']));

    $labels = statLabels();

    expect($labels)->toContain(__('Active Students'))
        ->and($labels)->toContain(__('Withdrawn'))
        ->and($labels)->not->toContain(__('Weekly Revenue'))
        ->and($labels)->not->toContain(__('Outstanding from Students'))
        ->and($labels)->not->toContain(__('Due Payments'))
        ->and($labels)->not->toContain(__('Open Complaints'))
        ->and($labels)->not->toContain(__('Sessions Today'));

    // The other dashboard widgets are hidden outright.
    expect(DuePaymentsWidget::canView())->toBeFalse()
        ->and(RegistrationsChartWidget::canView())->toBeFalse()
        ->and(AttendanceBreakdownWidget::canView())->toBeFalse();
});

test('a full-permission user still sees every dashboard stat', function () {
    $this->actingAs(dashboardUser(PermissionCatalog::allGates()));

    $labels = statLabels();

    expect($labels)->toContain(__('Active Students'))
        ->and($labels)->toContain(__('Weekly Revenue'))
        ->and($labels)->toContain(__('Open Complaints'))
        ->and($labels)->toContain(__('Sessions Today'));

    expect(OverviewStatsWidget::canView())->toBeTrue()
        ->and(DuePaymentsWidget::canView())->toBeTrue()
        ->and(RegistrationsChartWidget::canView())->toBeTrue()
        ->and(AttendanceBreakdownWidget::canView())->toBeTrue();
});

test('a user with no dashboard-relevant permission sees no stats but the page still loads', function () {
    $user = dashboardUser(['room.index']);

    $this->actingAs($user)->get('/admin')->assertStatus(200);

    expect(OverviewStatsWidget::canView())->toBeFalse()
        ->and(statLabels())->toBe([]);
});
