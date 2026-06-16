<?php

namespace Whilesmart\Webhooks\Console;

use Illuminate\Console\Command;
use Whilesmart\Webhooks\Jobs\DeliverWebhook;
use Whilesmart\Webhooks\Models\WebhookDelivery;

class RetryStuckDeliveries extends Command
{
    protected $signature = 'webhooks:retry-stuck';

    protected $description = 'Re-enqueue outgoing webhook deliveries whose next attempt is due';

    public function handle(): int
    {
        $due = WebhookDelivery::retryable()
            ->whereNotNull('next_attempt_at')
            ->where('next_attempt_at', '<=', now())
            ->get();

        foreach ($due as $delivery) {
            DeliverWebhook::dispatch($delivery->id)
                ->onConnection(config('webhooks.delivery.connection'))
                ->onQueue(config('webhooks.delivery.queue'));
        }

        $this->info("Re-enqueued {$due->count()} webhook deliveries.");

        return self::SUCCESS;
    }
}
