<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $resources = [
            'users', 'students', 'trainers', 'governorates', 'cities',
            'educational_levels', 'rooms', 'subjects', 'sections',
            'registrations', 'attendances', 'payment_types', 'roles',
        ];

        foreach ($resources as $resource) {
            foreach (['view', 'create', 'update', 'delete'] as $action) {
                Permission::firstOrCreate([
                    'name' => "{$action}_{$resource}",
                    'guard_name' => 'web',
                ]);
            }
        }

        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $manager = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web']);
        $employee = Role::firstOrCreate(['name' => 'employee', 'guard_name' => 'web']);

        $admin->syncPermissions(Permission::all());

        $manager->syncPermissions(
            Permission::query()
                ->where('name', 'like', 'view_%')
                ->orWhere('name', 'like', 'create_%')
                ->orWhere('name', 'like', 'update_%')
                ->get()
        );

        $employee->syncPermissions(
            Permission::query()->where('name', 'like', 'view_%')->get()
        );
    }
}
