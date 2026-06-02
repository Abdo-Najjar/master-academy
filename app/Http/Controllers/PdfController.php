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
        abort_unless(hexa()->can('registration.index'), 403);

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
        abort_unless(hexa()->can('student.index'), 403);

        $qr = base64_encode(
            QrCode::format('png')->size(200)->margin(1)->generate($student->student_number ?? (string) $student->id)
        );

        $html = View::make('pdf.student-card', [
            'student' => $student,
            'qrPng' => $qr,
        ])->render();

        $pdf = LaravelMpdf::loadHTML($html, [
            'mode' => 'utf-8',
            'format' => [85, 54], // ID-1 card size in mm
            'orientation' => 'L',
            'margin_left' => 4,
            'margin_right' => 4,
            'margin_top' => 4,
            'margin_bottom' => 4,
            'default_font' => 'dejavusans',
            'autoLangToFont' => true,
            'autoScriptToLang' => true,
            'directionality' => 'rtl',
        ]);

        return $pdf->stream('student-card-'.$student->id.'.pdf');
    }
}
