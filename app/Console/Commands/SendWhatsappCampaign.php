<?php

namespace App\Console\Commands;

use App\Models\WhatsappCampaign;
use App\Models\WhatsappCampaignRecipient;
use App\Services\WhatsAppService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Sends every pending recipient of a campaign one at a time, sleeping a
 * random 40-60s between each send so the linked WhatsApp number isn't
 * flagged/blocked for bulk messaging. Dispatched onto the queue (see
 * WhatsappCampaignService::launch()) so the admin UI stays responsive
 * while the campaign runs.
 *
 * Runs as a queued job, not an interactive console command, so $this->info()
 * etc. never reach anyone — every meaningful step is also logged via Log::
 * so progress/failures are visible in storage/logs without shell access.
 */
class SendWhatsappCampaign extends Command
{
    protected $signature = 'whatsapp:campaign:send {campaign : The WhatsappCampaign ID}';

    protected $description = 'Send a WhatsApp campaign to its recipients, throttled 40-60s apart';

    public function handle(): int
    {
        $campaign = WhatsappCampaign::find((int) $this->argument('campaign'));

        if (! $campaign) {
            Log::warning('WhatsApp campaign send: campaign not found', ['campaign_id' => $this->argument('campaign')]);
            return self::FAILURE;
        }

        $campaign->update([
            'status' => WhatsappCampaign::STATUS_RUNNING,
            'started_at' => $campaign->started_at ?? now(),
        ]);

        $recipients = $campaign->recipients()
            ->where('status', WhatsappCampaignRecipient::STATUS_PENDING)
            ->get();

        Log::info('WhatsApp campaign send started', ['campaign_id' => $campaign->id, 'recipient_count' => $recipients->count()]);

        foreach ($recipients as $index => $recipient) {
            // Re-check status each iteration in case the campaign was cancelled mid-run.
            $campaign->refresh();
            if ($campaign->status === WhatsappCampaign::STATUS_CANCELLED) {
                Log::info('WhatsApp campaign cancelled mid-run', ['campaign_id' => $campaign->id]);
                break;
            }

            $ok = WhatsAppService::send($recipient->phone, $campaign->message);

            $recipient->update([
                'status' => $ok ? WhatsappCampaignRecipient::STATUS_SENT : WhatsappCampaignRecipient::STATUS_FAILED,
                'sent_at' => now(),
            ]);

            $campaign->increment($ok ? 'sent_count' : 'failed_count');

            Log::info('WhatsApp campaign recipient processed', [
                'campaign_id' => $campaign->id,
                'recipient_id' => $recipient->id,
                'ok' => $ok,
            ]);

            $isLast = $index === $recipients->count() - 1;
            if (! $isLast) {
                sleep(random_int(
                    config('whatsapp.campaign_throttle_min_seconds', 40),
                    config('whatsapp.campaign_throttle_max_seconds', 60),
                ));
            }
        }

        $campaign->update([
            'status' => WhatsappCampaign::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        Log::info('WhatsApp campaign send finished', ['campaign_id' => $campaign->id]);

        return self::SUCCESS;
    }
}
