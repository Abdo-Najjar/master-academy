<?php

namespace App\Support;

/**
 * The trainer's cut, which centres quote as a fraction ("النصف", "الثلث") as
 * often as they quote a percentage ("40%", "45%").
 *
 * It is stored as one number — a percentage — so every calculation downstream
 * stays a single multiplication. The presets here are just the values that
 * number usually takes, offered as a picker so nobody has to type 33.3333 by
 * hand and nobody stores 33.33 by mistake.
 *
 * Precision is the whole point of four decimal places: a third stored as 33.33
 * pays 99.99 ₪ on a 300 ₪ fee, which is exactly the kind of missing piaster a
 * trainer notices. Stored as 33.3333 it pays 100.00 ₪.
 */
class TrainerRate
{
    /** How close a stored percentage must be to a preset to be shown as one. */
    public const TOLERANCE = 0.005;

    /**
     * Presets that are a named fraction: key => [numerator, denominator].
     *
     * @return array<string, array{int, int}>
     */
    public static function fractions(): array
    {
        return [
            'half' => [1, 2],
            'third' => [1, 3],
            'two_thirds' => [2, 3],
            'quarter' => [1, 4],
            'three_quarters' => [3, 4],
            'fifth' => [1, 5],
            // 40% — the centre's usual share, and the app's default rate.
            'two_fifths' => [2, 5],
            'sixth' => [1, 6],
            'tenth' => [1, 10],
        ];
    }

    /**
     * Presets that are just a common percentage. 45% is 9/20 — a fraction with
     * no name anybody uses — so it belongs here rather than being forced into
     * the list above.
     *
     * @return list<float>
     */
    public static function plainPercentages(): array
    {
        return [45.0];
    }

    /**
     * Every preset the picker offers, key => percentage, highest first so the
     * list reads as a scale.
     *
     * @return array<string, float>
     */
    public static function presets(): array
    {
        $presets = [];

        foreach (self::fractions() as $key => [$numerator, $denominator]) {
            // Four places is what the column holds; rounding here keeps the
            // value that reaches the database identical to the one shown.
            $presets[$key] = round($numerator / $denominator * 100, 4);
        }

        foreach (self::plainPercentages() as $percent) {
            $presets[self::plainKey($percent)] = (float) $percent;
        }

        arsort($presets);

        return $presets;
    }

    /** @return array<string, string> key => translated label */
    public static function options(): array
    {
        $options = [];

        foreach (self::presets() as $key => $percent) {
            $name = self::fractionName($key);

            $options[$key] = $name === null
                // A plain percentage says everything about itself.
                ? self::format($percent)
                : $name.' — '.self::format($percent);
        }

        return $options;
    }

    /** The percentage a preset key stands for, or null if unknown. */
    public static function percent(string $key): ?float
    {
        return self::presets()[$key] ?? null;
    }

    /**
     * The preset a stored percentage represents, or null when it is a rate the
     * user typed that is not on the list.
     */
    public static function match(float|string|null $percent): ?string
    {
        if ($percent === null || $percent === '') {
            return null;
        }

        $percent = (float) $percent;

        foreach (self::presets() as $key => $value) {
            if (abs($percent - $value) <= self::TOLERANCE) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Human-readable rate, with trailing zeros trimmed so 40.0000 reads "40%".
     *
     * The fraction's name is only added when the percentage alone is lossy —
     * "33.3333%" is a third rounded, and saying so helps. A rate like 75% or
     * 40% is exact on its own, and naming it "three quarters" would rewrite
     * every existing section's rate column for no gain.
     */
    public static function label(float|string|null $percent): ?string
    {
        if ($percent === null || $percent === '') {
            return null;
        }

        $formatted = self::format((float) $percent);

        if (! self::isRepeating((float) $percent)) {
            return $formatted;
        }

        $name = self::fractionName((string) self::match($percent), short: true);

        return $name === null ? $formatted : $name.' ('.$formatted.')';
    }

    /**
     * Whether the percentage needs more than two decimals to be itself — the
     * thirds and sixths, as opposed to exact values like 40% or 12.5%.
     */
    public static function isRepeating(float $percent): bool
    {
        return abs(round($percent, 2) - $percent) > 0.00001;
    }

    /** `40`, `33.3333`, `12.5` — never `40.0000`. */
    public static function format(float $percent): string
    {
        $text = rtrim(rtrim(number_format($percent, 4, '.', ''), '0'), '.');

        return ($text === '' ? '0' : $text).'%';
    }

    /** Stable key for a plain-percentage preset: 45 => `pct_45`. */
    protected static function plainKey(float $percent): string
    {
        return 'pct_'.str_replace('.', '_', rtrim(rtrim(number_format($percent, 4, '.', ''), '0'), '.'));
    }

    /**
     * The fraction's name, or null when the preset is a plain percentage.
     *
     * @param  bool  $short  drop the "(1/3)" part, for inline display
     */
    protected static function fractionName(string $key, bool $short = false): ?string
    {
        $names = [
            'half' => [__('Half'), __('Half (1/2)')],
            'third' => [__('Third'), __('Third (1/3)')],
            'two_thirds' => [__('Two thirds'), __('Two thirds (2/3)')],
            'quarter' => [__('Quarter'), __('Quarter (1/4)')],
            'three_quarters' => [__('Three quarters'), __('Three quarters (3/4)')],
            'fifth' => [__('Fifth'), __('Fifth (1/5)')],
            'two_fifths' => [__('Two fifths'), __('Two fifths (2/5)')],
            'sixth' => [__('Sixth'), __('Sixth (1/6)')],
            'tenth' => [__('Tenth'), __('Tenth (1/10)')],
        ];

        if (! isset($names[$key])) {
            return null;
        }

        return $names[$key][$short ? 0 : 1];
    }
}
