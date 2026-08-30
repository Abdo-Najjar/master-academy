<?php

namespace App\Support;

/**
 * The Arabic typefaces shipped in resources/fonts, and the mPDF config that
 * registers them.
 *
 * mPDF's bundled DejaVu Sans can draw Arabic, but it draws it badly: the gaps
 * either side of a non-joining letter are wide enough that "الرياضيات" reads as
 * two words. Every printed document in the panel therefore ships with a real
 * Arabic face rather than falling back to the default.
 */
class PdfFonts
{
    /**
     * The face printed documents use unless they ask for another.
     *
     * Not Cairo, tempting as it is: mPDF refuses to parse its GDEF table
     * ("contains MarkGlyphSets - Not tested yet") the moment a document mixes
     * enough shaping, so a sheet renders fine until the day it doesn't.
     */
    public const DEFAULT = 'tajawal';

    /**
     * mPDF font registration for the faces in resources/fonts.
     *
     * `useOTL` is what makes Arabic Arabic: without the OpenType layout tables
     * switched on, mPDF draws each letter in its isolated form and the words
     * come out as loose strings of characters. It is off by default for
     * anything mPDF did not ship itself, so every entry here has to ask for it.
     *
     * Amiri is the exception: mPDF's TTF parser throws on its GPOS table
     * ("Lookup Type 5, Format 3 not supported") the moment OTL is switched on,
     * so it keeps the unshaped rendering it has always had rather than taking
     * the whole certificate down with it.
     *
     * @return array<string, array<string, string|int>>
     */
    public static function data(): array
    {
        $shaped = fn (string $regular, string $bold): array => [
            'R' => $regular,
            'B' => $bold,
            'useOTL' => 0xFF,
        ];

        return [
            'cairo' => $shaped('Cairo-Regular.ttf', 'Cairo-Bold.ttf'),
            'tajawal' => $shaped('Tajawal-Regular.ttf', 'Tajawal-Bold.ttf'),
            'amiri' => ['R' => 'Amiri-Regular.ttf', 'B' => 'Amiri-Bold.ttf'],
            'almarai' => $shaped('Almarai-Regular.ttf', 'Almarai-Bold.ttf'),
            'ibmplexsansarabic' => $shaped('IBMPlexSansArabic-Regular.ttf', 'IBMPlexSansArabic-Bold.ttf'),
            'bahijthesansarabic' => $shaped('BahijTheSansArabic-Regular.ttf', 'BahijTheSansArabic-Bold.ttf'),
        ];
    }

    /**
     * Base mPDF config for an RTL A4 landscape sheet, with the Arabic faces
     * registered. `autoLangToFont` is deliberately off: it re-picks a font per
     * script and would put DejaVu back under the Arabic text.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function config(array $overrides = []): array
    {
        return array_merge([
            'mode' => 'utf-8',
            'format' => 'A4-L',
            'margin_left' => 8,
            'margin_right' => 8,
            'margin_top' => 10,
            'margin_bottom' => 10,
            'default_font' => self::DEFAULT,
            'autoLangToFont' => false,
            'autoScriptToLang' => false,
            'directionality' => 'rtl',
            'custom_font_dir' => resource_path('fonts').'/',
            'custom_font_data' => self::data(),
        ], $overrides);
    }
}
