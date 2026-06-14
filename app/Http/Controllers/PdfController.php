<?php

namespace App\Http\Controllers;

use App\Models\Registration;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Symfony\Component\HttpFoundation\Response;

class PdfController extends Controller
{
    /**
     * Printable receipt for a single registration. mPDF is used because
     * dompdf does not shape Arabic glyphs / RTL correctly.
     */
    public function receipt(Request $request, Registration $registration): Response
    {
        $this->authorizeHexaGate($request, 'registration.index');

        $registration->loadMissing(['student', 'section.subject', 'section.trainer', 'paymentType']);

        $html = View::make('pdf.receipt', [
            'registration' => $registration,
            'now' => now(),
            'issuer' => Auth::user(),
        ])->render();

        $pdf = LaravelMpdf::loadHTML($html, [
            'mode' => 'utf-8',
            'format' => 'A5',
            'orientation' => 'P',
            'default_font' => 'dejavusans',
            'autoLangToFont' => true,
            'autoScriptToLang' => true,
            'directionality' => 'rtl',
        ]);

        return $pdf->stream('receipt-'.$registration->id.'.pdf');
    }

    /**
     * Printable student ID card with a QR encoding the student number.
     */
    public function studentCard(Request $request, Student $student): Response
    {
        $this->authorizeHexaGate($request, 'student.index');

        $student->loadMissing(['governorate', 'city']);

        // SVG backend works without the imagick extension; mPDF renders inline SVG.
        $qrSvg = (string) QrCode::format('svg')->size(58)->margin(0)
            ->generate($student->student_number ?? (string) $student->id);
        $qrSvg = preg_replace('/^<\?xml[^>]*\?>\s*/', '', $qrSvg);

        // Embed the student photo (if any) as a data URI so mPDF renders it reliably.
        $photo = null;
        $media = $student->getFirstMedia('main');
        if ($media && is_file($media->getPath())) {
            $photo = 'data:'.$media->mime_type.';base64,'.base64_encode((string) file_get_contents($media->getPath()));
        }

        $name = $student->getTranslation('name', 'ar', false)
            ?: (is_array($student->name) ? reset($student->name) : $student->name);

        // Embed the center logo as a data URI for reliable mPDF rendering.
        $logo = null;
        $logoSetting = \App\Support\AppBranding::settings()['logo_path'] ?? null;
        $logoFile = $logoSetting && \Illuminate\Support\Facades\Storage::disk('public')->exists($logoSetting)
            ? \Illuminate\Support\Facades\Storage::disk('public')->path($logoSetting)
            : public_path('logo/logo.png');
        if (is_file($logoFile)) {
            $mime = str_ends_with(strtolower($logoFile), '.svg') ? 'image/svg+xml' : (mime_content_type($logoFile) ?: 'image/png');
            $logo = 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($logoFile));
        }

        $html = View::make('pdf.student-card', [
            'student' => $student,
            'qrSvg' => $qrSvg,
            'photo' => $photo,
            'name' => $name,
            'logo' => $logo,
        ])->render();

        $pdf = LaravelMpdf::loadHTML($html, [
            'mode' => 'utf-8',
            // Custom format array is [width, height]; keep orientation P or
            // mPDF swaps the dimensions again and the card comes out portrait.
            'format' => [85.6, 54], // ID-1 card size in mm
            'orientation' => 'P',
            'margin_left' => 0,
            'margin_right' => 0,
            'margin_top' => 0,
            'margin_bottom' => 0,
            'default_font' => 'dejavusans',
            'autoLangToFont' => true,
            'autoScriptToLang' => true,
            'directionality' => 'rtl',
        ]);

        return $pdf->stream('student-card-'.$student->id.'.pdf');
    }
}
