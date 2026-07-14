<?php

namespace App\Services;

use App\Models\Section;
use App\Models\Student;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    // -------------------------------------------------------------------------
    // Actual sending via Baileys CLI
    // -------------------------------------------------------------------------

    /**
     * Send a WhatsApp message by invoking the Node.js Baileys CLI.
     *
     * @param  string|null  $media  Absolute path to an attachment (image/video/PDF).
     * @return bool  True on success (exit 0), false on failure.
     */
    public static function send(string $phone, string $message, ?string $media = null): bool
    {
        if (app() instanceof \Illuminate\Foundation\Application && app()->runningUnitTests()) {
            return true;
        }

        $phone = self::normalizePhone($phone);
        if ($phone === '') {
            Log::warning('WhatsApp: empty phone after normalization — skipping');
            return false;
        }

        $cliPath    = escapeshellarg(base_path('whatsapp/cli.js'));
        $phoneArg   = escapeshellarg($phone);
        $messageArg = escapeshellarg($message);

        $command = 'node ' . $cliPath . ' send --phone ' . $phoneArg . ' --message ' . $messageArg;

        if ($media !== null && $media !== '') {
            $command .= ' --media ' . escapeshellarg($media);
        }

        $command .= ' 2>&1';

        exec($command, $output, $exitCode);

        Log::info('WhatsApp send', [
            'phone'    => substr($phone, 0, 6) . '…',
            'exitCode' => $exitCode,
            'output'   => $output,
        ]);

        return $exitCode === 0;
    }

    /**
     * Normalize any phone number to digits-only with international prefix.
     * Falls back to a 972 (Israel/Palestine) prefix when no country code present.
     */
    public static function normalizePhone(?string $raw, string $defaultCountry = '972'): string
    {
        $digits = preg_replace('/\D+/', '', (string) $raw);

        if ($digits === '') {
            return '';
        }

        // Already starts with a known country prefix → keep as-is
        if (str_starts_with($digits, '972') || str_starts_with($digits, '970')
            || str_starts_with($digits, '966') || str_starts_with($digits, '1')
        ) {
            return $digits;
        }

        return $defaultCountry . ltrim($digits, '0');
    }

    // -------------------------------------------------------------------------
    // wa.me URL helpers (for reports and click-to-chat links)
    // -------------------------------------------------------------------------

    public static function buildUrl(string $phone, string $message): string
    {
        $clean = self::normalizePhone($phone);
        if ($clean === '') {
            $clean = preg_replace('/[^0-9+]/', '', $phone);
            $clean = ltrim($clean, '+');
        }

        return 'https://wa.me/' . $clean . '?text=' . urlencode($message);
    }

    /**
     * Build contact list for all students/parents in a section.
     *
     * @return array<array{name: string, phone: string, url: string, type: string}>
     */
    public static function sectionContacts(Section $section, string $message): array
    {
        $contacts = [];

        foreach ($section->registrations()->with('student')->get() as $reg) {
            $student = $reg->student;
            if (! $student) {
                continue;
            }

            $name = is_array($student->name)
                ? ($student->name[app()->getLocale()] ?? reset($student->name))
                : (string) $student->name;

            if (filled($student->whatsapp_number) || filled($student->phone_number)) {
                $phone = $student->whatsapp_number ?: $student->phone_number;
                $contacts[] = [
                    'name'  => $name,
                    'phone' => $phone,
                    'url'   => self::buildUrl($phone, $message),
                    'type'  => 'student',
                ];
            }

            if (filled($student->parent_whatsapp) || filled($student->parent_phone)) {
                $phone = $student->parent_whatsapp ?: $student->parent_phone;
                $contacts[] = [
                    'name'  => ($student->parent_name ?: __('Parent')) . ' (ولي أمر ' . $name . ')',
                    'phone' => $phone,
                    'url'   => self::buildUrl($phone, $message),
                    'type'  => 'parent',
                ];
            }
        }

        return $contacts;
    }

    // -------------------------------------------------------------------------
    // Pre-built message templates
    // -------------------------------------------------------------------------

    public static function cancelSessionMessage(Section $section, string $date): string
    {
        $name = $section->getTranslation('name', 'ar', false) ?: $section->getTranslation('name', 'en', false);

        return "🔴 إشعار هام\n\nنعلمكم بأن حصة مجموعة ({$name}) المقررة بتاريخ {$date} قد تم إلغاؤها.\n\nعذراً على الإزعاج.";
    }

    public static function rescheduleMessage(Section $section, string $oldDate, string $newDate): string
    {
        $name = $section->getTranslation('name', 'ar', false) ?: $section->getTranslation('name', 'en', false);

        return "🔄 تغيير موعد\n\nنعلمكم بأن موعد حصة مجموعة ({$name}) قد تغيّر:\n📅 من: {$oldDate}\n📅 إلى: {$newDate}\n\nشكراً لتفهمكم.";
    }

    public static function paymentDueMessage(Student $student, string $sectionName, float $amount): string
    {
        $name = is_array($student->name)
            ? ($student->name['ar'] ?? reset($student->name))
            : (string) $student->name;

        return "💰 تذكير بالدفع\n\nعزيزنا ولي أمر الطالب/ة ({$name})\n\nنذكّركم بأن دفعة مجموعة ({$sectionName}) بقيمة (₪{$amount}) أصبحت مستحقة.\n\nيرجى التواصل مع الإدارة لإتمام الدفع.";
    }

    public static function absenceMessage(Student $student, Section $section, string $date): string
    {
        $studentName = is_array($student->name)
            ? ($student->name['ar'] ?? reset($student->name))
            : (string) $student->name;

        $sectionName = $section->getTranslation('name', 'ar', false) ?: $section->getTranslation('name', 'en', false);

        return "📋 إشعار غياب\n\nنعلمكم بأن الطالب/ة ({$studentName}) لم يحضر/تحضر حصة مجموعة ({$sectionName}) بتاريخ {$date}.\n\nللاستفسار تواصلوا مع الإدارة.";
    }
}
