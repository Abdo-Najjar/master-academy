<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Seeds every permission in PermissionCatalog, grants them all to a
 * "Super Admin" role, and attaches it to the user matching
 * config('app.super_admin_id', 1).
 */
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $gates = PermissionCatalog::allGates();

        foreach ($gates as $gate) {
            Permission::firstOrCreate(['name' => $gate, 'guard_name' => 'web']);
        }

        $role = Role::firstOrCreate(['name' => __('Super Admin'), 'guard_name' => 'web']);
        $role->syncPermissions($gates);

        $superAdminId = (int) config('app.super_admin_id', 1);
        $superAdmin = User::find($superAdminId);
        if ($superAdmin) {
            $superAdmin->assignRole($role);
        }
    }
}
