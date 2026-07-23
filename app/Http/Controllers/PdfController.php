<?php

namespace App\Http\Controllers;

use App\Filament\Admin\Pages\AttendanceRecords;
use App\Models\Certificate;
use App\Models\Registration;
use App\Models\Section;
use App\Models\Student;
use App\Services\CertificateService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Symfony\Component\HttpFoundation\Response;

class PdfController extends Controller
{
    /**
     * Client-side high-resolution PNG export of a certificate. The browser draws
     * the certificate on a Fabric canvas (crisp vector text + native-resolution
     * background) and downloads it as an image — no imagick/headless browser.
     */
    public function certificateImage(Request $request, Certificate $certificate): ViewContract
    {
        abort_unless($request->user()?->can('certificate.index'), 403);

        return View::make('pdf.certificate-image', [
            'payload' => CertificateService::imagePayload($certificate),
        ]);
    }

    /** Same export as certificateImage(), scoped to the student's own certificates. */
    public function studentCertificateImage(Request $request, Certificate $certificate): ViewContract
    {
        $student = Auth::guard('student')->user();
        abort_unless($student && $certificate->student_id === $student->id, 403);

        return View::make('pdf.certificate-image', [
            'payload' => CertificateService::imagePayload($certificate),
        ]);
    }

    /**
     * Printable receipt for a single registration. mPDF is used because
     * dompdf does not shape Arabic glyphs / RTL correctly.
     */
    public function receipt(Request $request, Registration $registration): Response
    {
        abort_unless($request->user()?->can('registration.index'), 403);

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
        abort_unless($request->user()?->can('student.index'), 403);

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
        // The card header is dark navy, so the light-on-dark logo variant is used.
        $logo = null;
        $logoFile = public_path('images/dark/android-chrome-512x512.png');
        if (is_file($logoFile)) {
            $mime = mime_content_type($logoFile) ?: 'image/png';
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

    /**
     * Printable daily attendance sheet for a single section: one row per
     * enrolled student with their recorded status for the given date.
     */
    public function attendanceSheet(Request $request, Section $section): Response
    {
        abort_unless($request->user()?->can('attendance.index'), 403);

        $data = $request->validate(['date' => 'required|date']);
        $date = Carbon::parse($data['date']);

        $section->loadMissing(['subject', 'trainer']);

        $attendances = $section->attendances()
            ->whereDate('date', $date)
            ->get()
            ->keyBy('student_id');

        $students = $section->registrations()
            ->with('student')
            ->get()
            ->pluck('student')
            ->filter()
            ->unique('id')
            ->sortBy(fn (Student $s): string => is_array($s->name) ? ($s->name['ar'] ?? reset($s->name)) : (string) $s->name)
            ->values();

        $presentCount = $students->filter(fn (Student $s): bool => in_array($attendances->get($s->id)?->status, ['present', 'late'], true))->count();
        $absentCount = $students->filter(fn (Student $s): bool => $attendances->get($s->id)?->status === 'absent')->count();
        $percent = $students->count() > 0 ? round($presentCount / $students->count() * 100) : 0;

        $translated = fn (mixed $value): ?string => is_array($value) ? ($value['ar'] ?? reset($value) ?: null) : $value;

        $sectionName = $translated($section->name) ?? (string) $section->id;

        $html = View::make('pdf.attendance-sheet', [
            'sectionName' => $sectionName,
            'subjectName' => $translated($section->subject?->name),
            'trainerName' => $translated($section->trainer?->name),
            'date' => $date,
            'students' => $students,
            'attendances' => $attendances,
            'labels' => AttendanceRecords::statusLabels(),
            'presentCount' => $presentCount,
            'absentCount' => $absentCount,
            'percent' => $percent,
        ])->render();

        $pdf = LaravelMpdf::loadHTML($html, [
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => 'P',
            'default_font' => 'dejavusans',
            'autoLangToFont' => true,
            'autoScriptToLang' => true,
            'directionality' => 'rtl',
        ]);

        return $pdf->stream('attendance-'.Str::slug($sectionName).'-'.$date->format('Y-m-d').'.pdf');
    }
}
