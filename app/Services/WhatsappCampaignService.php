<?php

namespace App\Services;

use App\Models\StudentGroup;
use App\Models\WhatsappCampaign;
use App\Models\WhatsappCampaignRecipient;
use Illuminate\Support\Facades\Log;

class WhatsappCampaignService
{
    /**
     * Snapshot the target group's students into campaign recipients
     * (name + normalized phone) so the send job has a stable list even if
     * group membership changes while the campaign is running.
     */
    public static function buildRecipients(WhatsappCampaign $campaign): int
    {
        $campaign->recipients()->delete();

        $group = $campaign->studentGroup;
        if (! $group instanceof StudentGroup) {
            return 0;
        }

        $count = 0;
        foreach ($group->students as $student) {
            $phone = $student->whatsapp_number ?: $student->phone_number;
            $normalized = WhatsAppService::normalizePhone($phone);

            if ($normalized === '') {
                continue;
            }

            $name = is_array($student->name)
                ? ($student->name[app()->getLocale()] ?? reset($student->name))
                : (string) $student->name;

            WhatsappCampaignRecipient::create([
                'whatsapp_campaign_id' => $campaign->id,
                'student_id' => $student->id,
                'name' => $name,
                'phone' => $normalized,
                'status' => WhatsappCampaignRecipient::STATUS_PENDING,
            ]);

            $count++;
        }

        $campaign->update(['total_count' => $count]);

        return $count;
    }

    /** Spawn the throttled sender as a detached background process. */
    public static function launch(WhatsappCampaign $campaign): void
    {
        $command = self::cliCommand($campaign->id);

        if (app()->runningUnitTests()) {
            return;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            pclose(popen('start /B "" ' . $command . ' > NUL 2>&1', 'r'));
        } else {
            exec($command . ' > /dev/null 2>&1 &');
        }

        Log::info('WhatsApp campaign launched', ['campaign_id' => $campaign->id]);
    }

    private static function cliCommand(int $campaignId): string
    {
        $php = escapeshellarg(PHP_BINARY);
        $artisan = escapeshellarg(base_path('artisan'));

        return $php . ' ' . $artisan . ' whatsapp:campaign:send ' . $campaignId;
    }
}
