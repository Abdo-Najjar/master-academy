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
        $qrPng = base64_encode(
            QrCode::format('png')->size(120)->margin(1)->generate($verifyUrl)
        );

        $fieldValues = [
            'student_name' => $student ? (is_array($student->name) ? ($student->name[app()->getLocale()] ?? reset($student->name)) : $student->name) : '',
            'section_name' => $section?->getTranslation('name', 'ar', false) ?? ($section?->getTranslation('name', 'en', false) ?? ''),
            'subject_name' => $section?->subject?->getTranslation('name', 'ar', false) ?? '',
            'serial_number' => $certificate->serial_number,
            'issued_date' => $certificate->issued_at?->format('Y/m/d') ?? now()->format('Y/m/d'),
            'student_number' => $student?->student_number ?? '',
        ];

        $html = view('pdf.certificate', [
            'certificate' => $certificate,
            'template' => $template,
            'bgDataUri' => $bgDataUri,
            'fieldValues' => $fieldValues,
            'qrPng' => $qrPng,
        ])->render();

        $w = (int) ($template->canvas_width / 3.7795);
        $h = (int) ($template->canvas_height / 3.7795);

        $pdf = LaravelMpdf::loadHTML($html, [
            'mode' => 'utf-8',
            'format' => [$w, $h],
            'orientation' => $w > $h ? 'L' : 'P',
            'margin_left' => 0,
            'margin_right' => 0,
            'margin_top' => 0,
            'margin_bottom' => 0,
            'default_font' => 'dejavusans',
            'autoLangToFont' => true,
            'autoScriptToLang' => true,
        ]);

        return $pdf->output();
    }
}
