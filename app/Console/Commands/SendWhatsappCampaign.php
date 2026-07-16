<?php

namespace App\Console\Commands;

use App\Models\WhatsappCampaign;
use App\Models\WhatsappCampaignRecipient;
use App\Services\WhatsAppService;
use Illuminate\Console\Command;

/**
 * Sends every pending recipient of a campaign one at a time, sleeping a
 * random 40-60s between each send so the linked WhatsApp number isn't
 * flagged/blocked for bulk messaging. Launched as a detached background
 * process (see WhatsappCampaignService::launch()) so the admin UI stays
 * responsive while the campaign runs — mirrors how WhatsappLinkService
 * spawns the linking CLI.
 */
class SendWhatsappCampaign extends Command
{
    protected $signature = 'whatsapp:campaign:send {campaign : The WhatsappCampaign ID}';

    protected $description = 'Send a WhatsApp campaign to its recipients, throttled 40-60s apart';

    public function handle(): int
    {
        $campaign = WhatsappCampaign::find((int) $this->argument('campaign'));

        if (! $campaign) {
            $this->error('Campaign not found.');
            return self::FAILURE;
        }

        $campaign->update([
            'status' => WhatsappCampaign::STATUS_RUNNING,
            'started_at' => $campaign->started_at ?? now(),
        ]);

        $recipients = $campaign->recipients()
            ->where('status', WhatsappCampaignRecipient::STATUS_PENDING)
            ->get();

        $this->info("Sending campaign #{$campaign->id} to {$recipients->count()} recipient(s)...");

        foreach ($recipients as $index => $recipient) {
            // Re-check status each iteration in case the campaign was cancelled mid-run.
            $campaign->refresh();
            if ($campaign->status === WhatsappCampaign::STATUS_CANCELLED) {
                $this->warn('Campaign cancelled — stopping.');
                break;
            }

            $ok = WhatsAppService::send($recipient->phone, $campaign->message);

            $recipient->update([
                'status' => $ok ? WhatsappCampaignRecipient::STATUS_SENT : WhatsappCampaignRecipient::STATUS_FAILED,
                'sent_at' => now(),
            ]);

            $campaign->increment($ok ? 'sent_count' : 'failed_count');

            $this->line(($ok ? '✓ sent' : '✗ failed') . " to {$recipient->name} ({$recipient->phone})");

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

        $this->info('Campaign finished.');

        return self::SUCCESS;
    }
}
