<?php

namespace Database\Seeders;

use App\Models\User;
use Hexters\HexaLite\Models\HexaRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds a Super Admin HexaLite role with every gate defined across Filament
 * resources + pages, then attaches it to the user matching
 * config('app.super_admin_id', 1). HexaLite stores `access` as a 2D map keyed
 * by a resource slug — each value is the list of gates granted under that
 * resource. The shape must match `gates.<slug>` as emitted by RoleForm.
 */
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $groupedGates = [
            'employee' => ['user.index', 'user.create', 'user.update', 'user.delete'],
            'student' => ['student.index', 'student.create', 'student.update', 'student.delete', 'student.wallet'],
            'trainer' => ['trainer.index', 'trainer.create', 'trainer.update', 'trainer.delete', 'trainer.wallet'],
            'subject' => ['subject.index', 'subject.create', 'subject.update', 'subject.delete'],
            'section' => ['section.index', 'section.create', 'section.update', 'section.delete'],
            'registration' => ['registration.index', 'registration.create', 'registration.update', 'registration.delete', 'registration.cancel'],
            'attendance' => ['attendance.index', 'attendance.update', 'attendance.delete'],
            'exam' => ['exam.index', 'exam.create', 'exam.update', 'exam.delete'],
            'city' => ['city.index', 'city.create', 'city.update', 'city.delete'],
            'governorate' => ['governorate.index', 'governorate.create', 'governorate.update', 'governorate.delete'],
            'room' => ['room.index', 'room.create', 'room.update', 'room.delete'],
            'payment_type' => ['payment_type.index', 'payment_type.create', 'payment_type.update', 'payment_type.delete'],
            'complaint' => ['complaint.index', 'complaint.update', 'complaint.delete'],
            'announcement' => ['announcement.index', 'announcement.create', 'announcement.update', 'announcement.delete'],
            'backup' => ['backup.run', 'backup.download', 'backup.delete'],
            'settings' => ['settings.manage'],
            'login_activity' => ['login_activity.index'],
            'reports' => ['reports.view'],
            'role' => ['role.index', 'role.create', 'role.update', 'role.delete'],
        ];

        $role = HexaRole::updateOrCreate(
            ['name' => __('Super Admin'), 'guard' => 'web'],
            [
                'uuid' => (string) Str::uuid(),
                'access' => $groupedGates,
                'gates' => $groupedGates,
                'checkall' => [],
            ]
        );

        $superAdminId = (int) config('app.super_admin_id', 1);
        $superAdmin = User::find($superAdminId);
        if ($superAdmin) {
            $role->users()->syncWithoutDetaching([$superAdmin->getKey()]);
        }
    }
}
