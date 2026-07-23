<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * One-time, idempotent migration of the legacy HexaLite role/permission data
 * (hexa_roles + hexa_role_user) into spatie/laravel-permission's tables.
 * Safe to re-run — existing spatie roles/permissions/assignments are reused
 * rather than duplicated.
 */
class MigrateHexaRolesToSpatie extends Command
{
    protected $signature = 'permissions:migrate-from-hexa';

    protected $description = 'Migrate hexa_roles/hexa_role_user data into spatie/laravel-permission tables';

    public function handle(): int
    {
        if (! Schema::hasTable('hexa_roles')) {
            $this->info('No hexa_roles table found — nothing to migrate.');

            return self::SUCCESS;
        }

        $hexaRoles = DB::table('hexa_roles')->get();

        foreach ($hexaRoles as $hexaRole) {
            $guard = $hexaRole->guard ?: 'web';
            $access = json_decode((string) $hexaRole->access, true) ?: [];

            $gates = collect($access)->flatten()->unique()->values()->all();

            $role = Role::firstOrCreate(['name' => $hexaRole->name, 'guard_name' => $guard]);

            foreach ($gates as $gate) {
                Permission::firstOrCreate(['name' => $gate, 'guard_name' => $guard]);
            }

            $role->syncPermissions($gates);

            $userIds = DB::table('hexa_role_user')->where('role_id', $hexaRole->id)->pluck('user_id');
            foreach ($userIds as $userId) {
                $user = User::find($userId);
                $user?->assignRole($role);
            }

            $this->info("Migrated role \"{$hexaRole->name}\" ({$role->permissions()->count()} permissions, {$userIds->count()} users).");
        }

        return self::SUCCESS;
    }
}
