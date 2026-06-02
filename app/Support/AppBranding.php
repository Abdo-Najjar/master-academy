<?php

namespace App\Support;

use App\Settings\AppSettings;
use Filament\Support\Colors\Color;
use Illuminate\Support\Facades\Storage;
use Throwable;

class AppBranding
{
    /**
     * Pull settings safely — returns defaults if the settings table is not yet seeded.
     */
    public static function settings(): array
    {
        try {
            $settings = app(AppSettings::class);

            return [
                'app_name' => $settings->app_name,
                'logo_path' => $settings->logo_path,
                'primary_color' => $settings->primary_color,
                'secondary_color' => $settings->secondary_color,
            ];
        } catch (Throwable) {
            return [
                'app_name' => config('app.name', 'منبع التميز'),
                'logo_path' => null,
                'primary_color' => '#dc2626',
                'secondary_color' => '#f59e0b',
            ];
        }
    }

    public static function logoUrl(): string
    {
        $logo = self::settings()['logo_path'];

        if ($logo && Storage::disk('public')->exists($logo)) {
            return Storage::disk('public')->url($logo);
        }

        return asset('logo/logo.png');
    }

    public static function appName(): string
    {
        return self::settings()['app_name'] ?? config('app.name', 'منبع التميز');
    }

    /**
     * Build the Filament colors map from the saved hex values. Uses
     * Color::hex() so Filament generates the full OKLCH palette + the
     * required --fi-color-XXX CSS variables (including the foreground
     * color that picks white vs black per shade).
     *
     * @return array<string, array<int, string>>
     */
    public static function panelColors(): array
    {
        $settings = self::settings();

        return [
            'primary' => Color::hex($settings['primary_color']),
            'secondary' => Color::hex($settings['secondary_color']),
        ];
    }
}
