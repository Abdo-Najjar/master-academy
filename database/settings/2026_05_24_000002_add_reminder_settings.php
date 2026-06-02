<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('app.enable_absence_alerts', true);
        $this->migrator->add('app.absence_alert_threshold', 3);
        $this->migrator->add('app.enable_unpaid_attendance_alerts', true);
        $this->migrator->add('app.unpaid_attendance_alert_threshold', 5);
    }
};
