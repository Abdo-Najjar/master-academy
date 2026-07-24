<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        if (! $this->migrator->exists('app.app_name')) {
            return;
        }

        $this->migrator->update('app.app_name', fn ($value) => $value === 'منبع التميز' ? 'ماستر أكاديمي' : $value);
    }
};
