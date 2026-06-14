<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Section;
use App\Models\Student;
use Illuminate\Support\Facades\Auth;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class CertificateService
{
    public static function issue(Student $student, CertificateTemplate $template, ?Section $section = null): Certificate
    {
        return Certificate::create([
            'student_id' => $student->id,
            'section_id' => $section?->id,
            'template_id' => $template->id,
            'issued_by' => Auth::id(),
        ]);
    }

    public static function generatePdf(Certificate $certificate): string
    {
        $certificate->loadMissing(['student', 'section.subject', 'template']);

        $template = $certificate->template;
        $student = $certificate->student;
        $section = $certificate->section;

        $bgMedia = $template->getFirstMedia('background');
        $bgDataUri = null;
        if ($bgMedia && is_file($bgMedia->getPath())) {
            $bgDataUri = 'data:'.$bgMedia->mime_type.';base64,'.base64_encode((string) file_get_contents($bgMedia->getPath()));
        }

        $verifyUrl = url('/certificates/verify/'.$certificate->verification_token);

        // SVG QR works without the imagick extension and renders crisply in mPDF.
        // Size it to the QR field placed in the design (or a sensible default).
        $qrField = collect($template->fields_config ?? [])->firstWhere('key', 'qr_code');
        $qrSize = $qrField ? (int) ($qrField['size'] ?? 140) : 90;
        $qrSvg = (string) QrCode::format('svg')->size($qrSize)->margin(0)->generate($verifyUrl);
        $qrSvg = preg_replace('/^<\?xml[^>]*\?>\s*/', '', $qrSvg);

        $fieldValues = self::resolveFieldValues($certificate, $student, $section);

        $html = view('pdf.certificate', [
            'certificate' => $certificate,
            'template' => $template,
            'bgDataUri' => $bgDataUri,
            'fieldValues' => $fieldValues,
            'qrSvg' => $qrSvg,
        ])->render();

        $w = (int) ($template->canvas_width / 3.7795);
        $h = (int) ($template->canvas_height / 3.7795);

        $pdf = LaravelMpdf::loadHTML($html, [
            'mode' => 'utf-8',
            // format is the literal [width, height]; keep orientation 'P' so mPDF
            // uses these as-is instead of swapping them (which produced a portrait
            // page for a landscape certificate, leaving white space).
            'format' => [$w, $h],
            'orientation' => 'P',
            'margin_left' => 0,
            'margin_right' => 0,
            'margin_top' => 0,
            'margin_bottom' => 0,
            'default_font' => 'dejavusans',
            'autoLangToFont' => true,
            'autoScriptToLang' => true,
            // Keep embedded background images crisp (default 96dpi downsamples them).
            'dpi' => 96,
            'img_dpi' => 300,
            'jpeg_quality' => 95,
        ]);

        return $pdf->output();
    }

    /**
     * Build the data a client-side renderer needs to draw the certificate on a
     * Fabric canvas and export a high-resolution PNG (crisp text + native-res
     * background, no imagick/headless browser required).
     *
     * @return array<string, mixed>
     */
    public static function imagePayload(Certificate $certificate): array
    {
        $certificate->loadMissing(['student', 'section.subject', 'template']);

        $template = $certificate->template;
        $student = $certificate->student;
        $section = $certificate->section;

        $values = self::resolveFieldValues($certificate, $student, $section);

        $verifyUrl = url('/certificates/verify/'.$certificate->verification_token);
        $qrField = collect($template->fields_config ?? [])->firstWhere('key', 'qr_code');
        $qrSize = $qrField ? (int) ($qrField['size'] ?? 140) : 0;
        $qrSvg = null;
        if ($qrSize > 0) {
            $qrSvg = (string) QrCode::format('svg')->size($qrSize)->margin(0)->generate($verifyUrl);
            $qrSvg = preg_replace('/^<\?xml[^>]*\?>\s*/', '', $qrSvg);
        }

        $fonts = [
            'dejavusans' => 'Arial, sans-serif',
            'dejavuserif' => 'Georgia, serif',
            'dejavusansmono' => '"Courier New", monospace',
        ];

        $fields = [];
        foreach ($template->fields_config ?? [] as $f) {
            $key = $f['key'] ?? '';
            if ($key === 'qr_code') {
                continue;
            }
            if (($values[$key] ?? '') === '') {
                continue;
            }
            $fields[] = [
                'value' => (string) $values[$key],
                'x' => (int) ($f['x'] ?? 0),
                'y' => (int) ($f['y'] ?? 0),
                'fontSize' => (int) ($f['font_size'] ?? 24),
                'fill' => (string) ($f['font_color'] ?? '#000000'),
                'fontWeight' => (string) ($f['font_weight'] ?? 'normal'),
                'fontFamily' => $fonts[$f['font_family'] ?? 'dejavusans'] ?? 'Arial, sans-serif',
                'textAlign' => (string) ($f['text_align'] ?? 'right'),
                'width' => (int) ($f['width'] ?? 0),
            ];
        }

        return [
            'width' => (int) $template->canvas_width,
            'height' => (int) $template->canvas_height,
            'bgUrl' => $template->getFirstMediaUrl('background') ?: null,
            'fields' => $fields,
            'qr' => $qrField ? [
                'x' => (int) ($qrField['x'] ?? 0),
                'y' => (int) ($qrField['y'] ?? 0),
                'size' => $qrSize,
                'svg' => $qrSvg,
            ] : null,
            'filename' => 'certificate-'.$certificate->serial_number.'.png',
        ];
    }

    /**
     * Resolve every supported field key to its concrete value for a certificate.
     *
     * @return array<string, string>
     */
    protected static function resolveFieldValues(Certificate $certificate, ?Student $student, ?Section $section): array
    {
        $studentNameAr = $student ? ((string) ($student->getTranslation('name', 'ar', false)
            ?: (is_array($student->name) ? reset($student->name) : $student->name))) : '';
        $studentNameEn = $student ? (string) $student->getTranslation('name', 'en', false) : '';
        $sectionNameAr = $section ? (string) $section->getTranslation('name', 'ar', false) : '';
        $sectionNameEn = $section ? (string) $section->getTranslation('name', 'en', false) : '';
        $subjectNameAr = $section?->subject ? (string) $section->subject->getTranslation('name', 'ar', false) : '';
        $subjectNameEn = $section?->subject ? (string) $section->subject->getTranslation('name', 'en', false) : '';

        return [
            // Locale-default keys (backward compatible with old templates)
            'student_name'  => $studentNameAr ?: $studentNameEn,
            'section_name'  => $sectionNameAr ?: $sectionNameEn,
            'subject_name'  => $subjectNameAr ?: $subjectNameEn,
            // Explicit per-language keys
            'student_name_ar' => $studentNameAr,
            'student_name_en' => $studentNameEn,
            'section_name_ar' => $sectionNameAr,
            'section_name_en' => $sectionNameEn,
            'subject_name_ar' => $subjectNameAr,
            'subject_name_en' => $subjectNameEn,
            // Other fields
            'serial_number' => $certificate->serial_number,
            'issued_date' => $certificate->issued_at?->format('Y/m/d') ?? now()->format('Y/m/d'),
            'student_number' => $student?->student_number ?? '',
            'student_ssn' => $student?->ssn ?? '',
        ];
    }
}
