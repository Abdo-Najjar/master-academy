<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Brings the permissions table in line with PermissionCatalog, which is the
 * single source of truth. Safe to re-run after every deploy: it only creates
 * what is missing and never touches a role's grants beyond the Super Admin
 * role, unless --prune is passed.
 */
class SyncPermissions extends Command
{
    protected $signature = 'permissions:sync
        {--prune : Also delete permissions that are no longer in the catalog}
        {--dry-run : Report what would change without writing anything}';

    protected $description = 'Sync system permissions from PermissionCatalog and re-grant them to the Super Admin role';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Dry-run mode — nothing will be written.');
        }

        $guard = 'web';
        $catalog = PermissionCatalog::allGates();
        $existing = Permission::query()->where('guard_name', $guard)->pluck('name')->all();

        $missing = array_values(array_diff($catalog, $existing));
        $stale = array_values(array_diff($existing, $catalog));

        // Create
        foreach ($missing as $gate) {
            $this->line("  + {$gate}");

            if (! $dryRun) {
                Permission::create(['name' => $gate, 'guard_name' => $guard]);
            }
        }

        $this->info(count($missing).' permission(s) created.');

        // Prune
        if ($stale !== []) {
            if ($this->option('prune')) {
                foreach ($stale as $gate) {
                    $this->line("  - {$gate}");

                    if (! $dryRun) {
                        Permission::query()->where('guard_name', $guard)->where('name', $gate)->delete();
                    }
                }

                $this->info(count($stale).' stale permission(s) deleted.');
            } else {
                $this->warn(count($stale).' permission(s) exist in the database but not in the catalog:');

                foreach ($stale as $gate) {
                    $this->line("  ? {$gate}");
                }

                $this->line('  Re-run with --prune to delete them.');
            }
        }

        // The Super Admin role must always hold every gate, otherwise a new
        // module silently becomes invisible to the people who administer it.
        $roleName = __('Super Admin');

        if (! $dryRun) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => $guard]);
            $role->syncPermissions($catalog);

            $superAdminId = (int) config('app.super_admin_id', 1);

            if ($superAdmin = User::find($superAdminId)) {
                $superAdmin->assignRole($role);
                $this->info("Role \"{$roleName}\" synced and assigned to user #{$superAdminId}.");
            } else {
                $this->warn("Role \"{$roleName}\" synced, but no user #{$superAdminId} exists to assign it to.");
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();
            $this->info('Permission cache cleared.');
        }

        $this->newLine();
        $this->info('Total gates in catalog: '.count($catalog));

        return self::SUCCESS;
    }
}
