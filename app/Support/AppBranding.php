<?php

namespace App\Support;

use App\Settings\AppSettings;
use Filament\Support\Colors\Color;
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
                'primary_color' => $settings->primary_color,
                'secondary_color' => $settings->secondary_color,
            ];
        } catch (Throwable) {
            return [
                'app_name' => config('app.name', 'منبع التميز'),
                'primary_color' => '#dc2626',
                'secondary_color' => '#f59e0b',
            ];
        }
    }

    /**
     * Static per-theme logo, generated once and stored in public/images/{light,dark}.
     */
    public static function logoUrl(string $theme = 'light'): string
    {
        $theme = $theme === 'dark' ? 'dark' : 'light';

        return asset("images/{$theme}/android-chrome-512x512.png");
    }

    /**
     * Static per-theme favicon, generated once and stored in public/images/{light,dark}.
     */
    public static function faviconUrl(string $theme = 'light'): string
    {
        $theme = $theme === 'dark' ? 'dark' : 'light';

        return asset("images/{$theme}/favicon.ico");
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
