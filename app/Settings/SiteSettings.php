<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Editable copy for the public marketing site. Anything that is a repeatable
 * record (programs, trainers, testimonials, gallery, branches) lives in its own
 * table instead; this holds the one-off headline copy and contact details.
 */
class SiteSettings extends Settings
{
    public string $hero_eyebrow;

    public string $hero_title;

    public string $hero_title_highlight;

    public string $hero_lead;

    public string $hero_badge_title;

    public string $hero_badge_note;

    /**
     * Headline numbers shown in the impact strip; each entry is a
     * `value` / `label` pair.
     *
     * Left untyped on purpose: spatie/laravel-settings reads the `@var`
     * annotation to pick a cast, and a generic array type makes it look for a
     * cast for the inner type and fail.
     */
    public array $stats;

    public string $about_text;

    /** Short selling points listed under the About section; a list of strings. */
    public array $about_values;

    public string $director_name;

    public string $director_role;

    public string $director_quote;

    public string $contact_phone;

    public string $contact_whatsapp;

    public string $license_number;

    public static function group(): string
    {
        return 'site';
    }
}
