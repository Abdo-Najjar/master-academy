<?php

namespace App\Jobs;

use App\Models\WhatsappCampaign;
use App\Models\WhatsappCampaignRecipient;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends one campaign message to one recipient. WhatsappCampaignService::launch()
 * dispatches one of these per recipient with a staggered ->delay() so the
 * 40-60s throttle between sends comes from the queue itself — a single worker
 * is only busy for the length of one send, not the whole campaign, and stays
 * free to pick up other queued work in between.
 */
class SendWhatsappCampaignMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(
        public int $campaignId,
        public int $recipientId,
    ) {
    }

    public function handle(): void
    {
        $campaign = WhatsappCampaign::find($this->campaignId);
        $recipient = WhatsappCampaignRecipient::find($this->recipientId);

        if (! $campaign || ! $recipient) {
            return;
        }

        if ($campaign->status === WhatsappCampaign::STATUS_CANCELLED) {
            Log::info('WhatsApp campaign recipient skipped: campaign cancelled', [
                'campaign_id' => $campaign->id,
                'recipient_id' => $recipient->id,
            ]);

            return;
        }

        if ($recipient->status !== WhatsappCampaignRecipient::STATUS_PENDING) {
            return;
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

        $this->completeCampaignIfFinished($campaign);
    }

    public function failed(?Throwable $exception): void
    {
        WhatsappCampaignRecipient::find($this->recipientId)?->update([
            'status' => WhatsappCampaignRecipient::STATUS_FAILED,
            'sent_at' => now(),
        ]);

        $campaign = WhatsappCampaign::find($this->campaignId);
        $campaign?->increment('failed_count');

        Log::error('WhatsApp campaign recipient job failed', [
            'campaign_id' => $this->campaignId,
            'recipient_id' => $this->recipientId,
            'error' => $exception?->getMessage(),
        ]);

        if ($campaign) {
            $this->completeCampaignIfFinished($campaign);
        }
    }

    private function completeCampaignIfFinished(WhatsappCampaign $campaign): void
    {
        $campaign->refresh();

        if ($campaign->status !== WhatsappCampaign::STATUS_RUNNING) {
            return;
        }

        $stillPending = $campaign->recipients()
            ->where('status', WhatsappCampaignRecipient::STATUS_PENDING)
            ->exists();

        if ($stillPending) {
            return;
        }

        $campaign->update([
            'status' => WhatsappCampaign::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        Log::info('WhatsApp campaign send finished', ['campaign_id' => $campaign->id]);
    }
}
