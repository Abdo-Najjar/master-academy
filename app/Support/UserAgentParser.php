<?php

namespace App\Support;

/**
 * Tiny user-agent parser. Detects the common browsers, OSes, and a coarse
 * device class. Good enough for an audit log — we don't ship a full UA
 * database to avoid the dependency.
 */
class UserAgentParser
{
    public static function parse(?string $ua): array
    {
        $ua = (string) $ua;

        return [
            'browser' => self::browser($ua),
            'platform' => self::platform($ua),
            'device' => self::device($ua),
        ];
    }

    protected static function browser(string $ua): string
    {
        return match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'OPR/') || str_contains($ua, 'Opera') => 'Opera',
            str_contains($ua, 'Chrome/') && ! str_contains($ua, 'Chromium') => 'Chrome',
            str_contains($ua, 'Chromium/') => 'Chromium',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Safari/') && ! str_contains($ua, 'Chrome') => 'Safari',
            str_contains($ua, 'MSIE') || str_contains($ua, 'Trident') => 'Internet Explorer',
            $ua === '' => 'Unknown',
            default => 'Other',
        };
    }

    protected static function platform(string $ua): string
    {
        return match (true) {
            // iPhone/iPad UAs contain "Mac OS X" too — check iOS first.
            str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') || str_contains($ua, 'iPod') => 'iOS',
            str_contains($ua, 'Windows NT 10') => 'Windows 10/11',
            str_contains($ua, 'Windows NT') => 'Windows',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'Mac OS X') || str_contains($ua, 'macOS') => 'macOS',
            str_contains($ua, 'Linux') => 'Linux',
            $ua === '' => 'Unknown',
            default => 'Other',
        };
    }

    protected static function device(string $ua): string
    {
        if (str_contains($ua, 'iPhone') || (str_contains($ua, 'Android') && str_contains($ua, 'Mobile'))) {
            return 'Mobile';
        }
        if (str_contains($ua, 'iPad') || (str_contains($ua, 'Android') && ! str_contains($ua, 'Mobile'))) {
            return 'Tablet';
        }

        return 'Desktop';
    }
}
