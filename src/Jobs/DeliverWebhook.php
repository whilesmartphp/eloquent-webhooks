<?php

namespace Whilesmart\Webhooks\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Whilesmart\Webhooks\Events\WebhookDelivered;
use Whilesmart\Webhooks\Events\WebhookDeliveryFailed;
use Whilesmart\Webhooks\Events\WebhookDisabled;
use Whilesmart\Webhooks\Models\Webhook;
use Whilesmart\Webhooks\Models\WebhookDelivery;

class DeliverWebhook implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public int $deliveryId)
    {
    }

    public function handle(): void
    {
        $delivery = WebhookDelivery::with('webhook')->find($this->deliveryId);

        if (! $delivery || $delivery->status === WebhookDelivery::STATUS_DELIVERED) {
            return;
        }

        $webhook = $delivery->webhook;

        if (! $webhook || ! $webhook->is_active) {
            $delivery->update([
                'status' => WebhookDelivery::STATUS_FAILED,
                'error' => 'Webhook missing or inactive',
            ]);

            return;
        }

        $attempt = $delivery->attempts + 1;
        $delivery->update([
            'status' => WebhookDelivery::STATUS_DELIVERING,
            'attempts' => $attempt,
        ]);

        $body = json_encode($delivery->payload ?? [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $timestamp = (string) now()->getTimestamp();
        $signature = $webhook->sign($timestamp, $body);

        try {
            $response = Http::timeout((int) config('webhooks.delivery.timeout', 10))
                ->withBody($body, 'application/json')
                ->withHeaders([
                    'X-Webhook-Id' => (string) $webhook->id,
                    'X-Webhook-Event' => $delivery->event,
                    'X-Webhook-Delivery' => $delivery->event_id,
                    'X-Webhook-Timestamp' => $timestamp,
                    config('webhooks.signing.header', 'X-Webhook-Signature') => config('webhooks.signing.algo', 'sha256') . '=' . $signature,
                ])
                ->post($webhook->url);
        } catch (\Throwable $e) {
            $this->handleFailure($delivery, $webhook, null, $e->getMessage());

            return;
        }

        if ($response->successful()) {
            $delivery->update([
                'status' => WebhookDelivery::STATUS_DELIVERED,
                'response_status' => $response->status(),
                'response_body' => $this->truncate($response->body()),
                'error' => null,
                'next_attempt_at' => null,
                'delivered_at' => now(),
            ]);

            if ($webhook->consecutive_failures > 0) {
                $webhook->update(['consecutive_failures' => 0]);
            }

            WebhookDelivered::dispatch($delivery);

            return;
        }

        $this->handleFailure($delivery, $webhook, $response->status(), $this->truncate($response->body()));
    }

    protected function handleFailure(WebhookDelivery $delivery, Webhook $webhook, ?int $status, ?string $body): void
    {
        $maxAttempts = (int) config('webhooks.delivery.max_attempts', 6);
        $backoff = config('webhooks.delivery.backoff', [10, 60, 300, 1800, 7200, 21600]);

        if ($delivery->attempts < $maxAttempts) {
            $delay = $backoff[$delivery->attempts - 1] ?? end($backoff);

            $delivery->update([
                'status' => WebhookDelivery::STATUS_PENDING,
                'response_status' => $status,
                'response_body' => $body,
                'error' => $body ?? 'Delivery failed',
                'next_attempt_at' => now()->addSeconds($delay),
            ]);

            self::dispatch($delivery->id)
                ->onConnection(config('webhooks.delivery.connection'))
                ->onQueue(config('webhooks.delivery.queue'))
                ->delay(now()->addSeconds($delay));

            return;
        }

        $delivery->update([
            'status' => WebhookDelivery::STATUS_FAILED,
            'response_status' => $status,
            'response_body' => $body,
            'error' => $body ?? 'Delivery failed',
            'next_attempt_at' => null,
        ]);

        $failures = $webhook->consecutive_failures + 1;
        $webhook->update(['consecutive_failures' => $failures]);

        WebhookDeliveryFailed::dispatch($delivery);

        $autoDisable = (int) config('webhooks.auto_disable_after', 15);
        if ($autoDisable > 0 && $failures >= $autoDisable && $webhook->is_active) {
            $webhook->update(['is_active' => false]);
            WebhookDisabled::dispatch($webhook);
        }
    }

    protected function truncate(?string $body): ?string
    {
        return $body === null ? null : mb_substr($body, 0, 2000);
    }
}
