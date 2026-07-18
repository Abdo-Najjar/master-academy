<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class AppSettings extends Settings
{
    public string $app_name;

    public string $primary_color;

    public string $secondary_color;

    public bool $enable_absence_alerts;

    public int $absence_alert_threshold;

    public bool $enable_unpaid_attendance_alerts;

    public int $unpaid_attendance_alert_threshold;

    public int $sibling_discount_percent;

    public static function group(): string
    {
        return 'app';
    }
}
