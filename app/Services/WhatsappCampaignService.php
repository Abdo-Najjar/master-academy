<?php

namespace App\Services;

use App\Jobs\SendWhatsappCampaignMessage;
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

    /**
     * Dispatch one queued job per pending recipient, each delayed further
     * than the last by a random 40-60s, so the 40-60s-apart throttle comes
     * from the queue's own delay instead of a single job sleeping through
     * the whole campaign — that tied up a queue worker for the campaign's
     * entire duration and kept it from picking up anything else meanwhile.
     */
    public static function launch(WhatsappCampaign $campaign): void
    {
        $recipients = $campaign->recipients()
            ->where('status', WhatsappCampaignRecipient::STATUS_PENDING)
            ->orderBy('id')
            ->get();

        $delaySeconds = 0;

        foreach ($recipients as $recipient) {
            SendWhatsappCampaignMessage::dispatch($campaign->id, $recipient->id)
                ->delay(now()->addSeconds($delaySeconds));

            $delaySeconds += random_int(
                config('whatsapp.campaign_throttle_min_seconds', 40),
                config('whatsapp.campaign_throttle_max_seconds', 60),
            );
        }

        Log::info('WhatsApp campaign messages queued', [
            'campaign_id' => $campaign->id,
            'recipient_count' => $recipients->count(),
        ]);
    }
}
