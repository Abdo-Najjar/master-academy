<?php

namespace App\Services;

use App\Models\StudentGroup;
use App\Models\WhatsappCampaign;
use App\Models\WhatsappCampaignRecipient;
use Illuminate\Support\Facades\Artisan;
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
     * Dispatch the throttled sender onto Laravel's queue so it runs in the
     * background without blocking the request. This replaces an earlier
     * approach that shelled out directly (exec()/popen() spawning
     * `php artisan ...`) — that broke in production because PHP_BINARY under
     * php-fpm resolves to the php-fpm binary, not a usable CLI interpreter,
     * so the spawn silently did nothing. Artisan::queue() sidesteps shell
     * spawning entirely and relies on the app's own queue worker instead.
     */
    public static function launch(WhatsappCampaign $campaign): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        Artisan::queue('whatsapp:campaign:send', ['campaign' => $campaign->id]);

        Log::info('WhatsApp campaign queued', ['campaign_id' => $campaign->id]);
    }
}
