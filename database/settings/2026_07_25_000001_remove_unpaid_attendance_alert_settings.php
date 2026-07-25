<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->deleteIfExists('app.enable_unpaid_attendance_alerts');
        $this->migrator->deleteIfExists('app.unpaid_attendance_alert_threshold');
    }
};
