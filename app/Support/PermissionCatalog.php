<?php

namespace App\Support;

/**
 * Single source of truth for every permission gate string used across the
 * admin panel, grouped by module. Consumed by PermissionSeeder (to seed
 * Permission records) and the Roles Filament resource (to render the
 * grouped permission checklist). Mirrors the gates previously declared via
 * HexaLite's per-resource defineGates().
 */
class PermissionCatalog
{
    /** @return array<string, array<string, string>> module => [gate => label] */
    public static function all(): array
    {
        return [
            'employee' => [
                'user.index' => __('View'),
                'user.create' => __('Create'),
                'user.update' => __('Update'),
                'user.delete' => __('Delete'),
            ],
            'student' => [
                'student.index' => __('View'),
                'student.create' => __('Create'),
                'student.update' => __('Update'),
                'student.delete' => __('Delete'),
                'student.wallet' => __('Manage Wallet'),
            ],
            'trainer' => [
                'trainer.index' => __('View'),
                'trainer.create' => __('Create'),
                'trainer.update' => __('Update'),
                'trainer.delete' => __('Delete'),
                'trainer.wallet' => __('Manage Wallet'),
            ],
            'subject' => [
                'subject.index' => __('View'),
                'subject.create' => __('Create'),
                'subject.update' => __('Update'),
                'subject.delete' => __('Delete'),
            ],
            'section' => [
                'section.index' => __('View'),
                'section.create' => __('Create'),
                'section.update' => __('Update'),
                'section.delete' => __('Delete'),
            ],
            'registration' => [
                'registration.index' => __('View'),
                'registration.create' => __('Create'),
                'registration.update' => __('Update'),
                'registration.delete' => __('Delete'),
                'registration.cancel' => __('Cancel & Refund'),
            ],
            'attendance' => [
                'attendance.index' => __('View'),
                'attendance.update' => __('Update'),
                'attendance.delete' => __('Delete'),
            ],
            'exam' => [
                'exam.index' => __('View'),
                'exam.create' => __('Create'),
                'exam.update' => __('Update'),
                'exam.delete' => __('Delete'),
            ],
            'assignment' => [
                'assignment.index' => __('View'),
                'assignment.create' => __('Create'),
                'assignment.update' => __('Update'),
                'assignment.delete' => __('Delete'),
            ],
            'city' => [
                'city.index' => __('View'),
                'city.create' => __('Create'),
                'city.update' => __('Update'),
                'city.delete' => __('Delete'),
            ],
            'governorate' => [
                'governorate.index' => __('View'),
                'governorate.create' => __('Create'),
                'governorate.update' => __('Update'),
                'governorate.delete' => __('Delete'),
            ],
            'room' => [
                'room.index' => __('View'),
                'room.create' => __('Create'),
                'room.update' => __('Update'),
                'room.delete' => __('Delete'),
            ],
            'payment_type' => [
                'payment_type.index' => __('View'),
                'payment_type.create' => __('Create'),
                'payment_type.update' => __('Update'),
                'payment_type.delete' => __('Delete'),
            ],
            'exemption_type' => [
                'exemption_type.index' => __('View'),
                'exemption_type.create' => __('Create'),
                'exemption_type.update' => __('Update'),
                'exemption_type.delete' => __('Delete'),
            ],
            'wallet_transactions' => [
                'wallet_transactions.index' => __('View Payment Operations'),
            ],
            'complaint' => [
                'complaint.index' => __('View'),
                'complaint.update' => __('Update / Respond'),
                'complaint.delete' => __('Delete'),
            ],
            'announcement' => [
                'announcement.index' => __('View'),
                'announcement.create' => __('Create'),
                'announcement.update' => __('Update'),
                'announcement.delete' => __('Delete'),
            ],
            'student_group' => [
                'student_group.index' => __('View'),
                'student_group.create' => __('Create'),
                'student_group.update' => __('Update'),
                'student_group.delete' => __('Delete'),
            ],
            'whatsapp_campaign' => [
                'whatsapp_campaign.index' => __('View'),
                'whatsapp_campaign.create' => __('Create'),
                'whatsapp_campaign.delete' => __('Delete'),
            ],
            'whatsapp' => [
                'whatsapp.manage' => __('Manage WhatsApp'),
            ],
            'quick_enroll' => [
                'quick-enroll.access' => __('Access'),
            ],
            'certificate' => [
                'certificate.index' => __('View'),
                'certificate.create' => __('Issue'),
                'certificate.delete' => __('Delete'),
            ],
            'certificate_template' => [
                'certificate_template.index' => __('View'),
                'certificate_template.create' => __('Create'),
                'certificate_template.update' => __('Update'),
                'certificate_template.delete' => __('Delete'),
            ],
            'backup' => [
                'backup.run' => __('Create Backup'),
                'backup.download' => __('Download Backup'),
                'backup.delete' => __('Delete Backup'),
            ],
            'settings' => [
                'settings.manage' => __('Manage App Settings'),
            ],
            'login_activity' => [
                'login_activity.index' => __('View Login Activities'),
            ],
            'reports' => [
                'reports.view' => __('View Reports'),
            ],
            'role' => [
                'role.index' => __('View'),
                'role.create' => __('Create'),
                'role.update' => __('Update'),
                'role.delete' => __('Delete'),
            ],
        ];
    }

    /** @return list<string> every gate string, flattened. */
    public static function allGates(): array
    {
        return collect(self::all())->flatMap(fn (array $gates) => array_keys($gates))->values()->all();
    }

    /** @return array<string, string> module key => display label. */
    public static function moduleLabels(): array
    {
        return [
            'employee' => __('Employees'),
            'student' => __('Students'),
            'trainer' => __('Trainers'),
            'subject' => __('Subjects'),
            'section' => __('Sections'),
            'registration' => __('Registrations'),
            'attendance' => __('Attendance'),
            'exam' => __('Exams'),
            'assignment' => __('Assignments'),
            'city' => __('Cities'),
            'governorate' => __('Governorates'),
            'room' => __('Rooms'),
            'payment_type' => __('Payment Types'),
            'exemption_type' => __('Exemption Types'),
            'wallet_transactions' => __('Wallet Transactions'),
            'complaint' => __('Complaints'),
            'announcement' => __('Announcements'),
            'student_group' => __('Student Groups'),
            'whatsapp_campaign' => __('WhatsApp Campaigns'),
            'whatsapp' => __('WhatsApp Settings'),
            'quick_enroll' => __('Quick Enroll'),
            'certificate' => __('Certificates'),
            'certificate_template' => __('Certificate Templates'),
            'backup' => __('System Backup'),
            'settings' => __('App Settings'),
            'login_activity' => __('Login Activities'),
            'reports' => __('Reports'),
            'role' => __('Roles & Permissions'),
        ];
    }
}
