<?php

namespace Whilesmart\Webhooks\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Whilesmart\Webhooks\Jobs\DeliverWebhook;
use Whilesmart\Webhooks\Models\Webhook;
use Whilesmart\Webhooks\Models\WebhookDelivery;

class WebhookDispatcher
{
    /**
     * @return Collection<int, WebhookDelivery>
     */
    public function dispatch(string $event, array $payload, array $filters = []): Collection
    {
        $webhooks = Webhook::query()
            ->where('direction', Webhook::DIRECTION_OUTGOING)
            ->where('is_active', true)
            ->where($filters)
            ->get();

        $deliveries = collect();

        foreach ($webhooks as $webhook) {
            /** @var Webhook $webhook */
            if ($webhook->listensFor($event)) {
                $deliveries->push($this->queue($webhook, $event, $payload));
            }
        }

        return $deliveries;
    }

    public function queue(Webhook $webhook, string $event, array $payload): WebhookDelivery
    {
        $delivery = WebhookDelivery::create([
            'webhook_id' => $webhook->id,
            'event' => $event,
            'event_id' => (string) Str::uuid(),
            'payload' => $payload,
            'status' => WebhookDelivery::STATUS_PENDING,
        ]);

        DeliverWebhook::dispatch($delivery->id)
            ->onConnection(config('webhooks.delivery.connection'))
            ->onQueue(config('webhooks.delivery.queue'));

        return $delivery;
    }

    public function redeliver(WebhookDelivery $delivery): ?WebhookDelivery
    {
        $webhook = $delivery->webhook;

        if (! $webhook) {
            return null;
        }

        return $this->queue($webhook, $delivery->event, $delivery->payload ?? []);
    }
}
