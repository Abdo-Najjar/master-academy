<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('app.app_name', config('app.name', 'منبع التميز'));
        $this->migrator->add('app.logo_path', null);
        $this->migrator->add('app.primary_color', '#dc2626'); // tailwind red-600
        $this->migrator->add('app.secondary_color', '#f59e0b'); // tailwind amber-500
    }
};
