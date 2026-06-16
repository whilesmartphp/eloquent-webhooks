<?php

namespace Whilesmart\Webhooks\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Whilesmart\Webhooks\Events\WebhookDisabled;
use Whilesmart\Webhooks\Models\Webhook;
use Whilesmart\Webhooks\Models\WebhookDelivery;
use Whilesmart\Webhooks\Services\WebhookDispatcher;
use Whilesmart\Webhooks\Tests\TestCase;
use Workbench\App\Models\User;

class OutboundDeliveryTest extends TestCase
{
    protected function defineEnvironment($app)
    {
        // Run delivery jobs inline so the outbound behaviour is exercised deterministically.
        $app['config']->set('queue.default', 'sync');
    }

    private function makeUser(): User
    {
        return User::forceCreate([
            'name' => 'Owner',
            'email' => 'owner+'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
        ]);
    }

    private function outgoingWebhook(array $overrides = []): Webhook
    {
        $owner = $overrides['owner'] ?? $this->makeUser();
        unset($overrides['owner']);

        return Webhook::create(array_merge([
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => $owner->id,
            'created_by_type' => $owner->getMorphClass(),
            'created_by_id' => $owner->id,
            'name' => 'Customer endpoint',
            'direction' => Webhook::DIRECTION_OUTGOING,
            'url' => 'https://customer.example.com/hooks',
            'subscribed_events' => ['whatsapp.message.received'],
            'is_active' => true,
        ], $overrides));
    }

    #[Test]
    public function it_generates_a_secret_and_keeps_the_customer_url_for_outgoing_webhooks(): void
    {
        $webhook = $this->outgoingWebhook();

        $this->assertNotEmpty($webhook->secret);
        $this->assertSame('https://customer.example.com/hooks', $webhook->url);
        $this->assertNull($webhook->token);
    }

    #[Test]
    public function it_delivers_to_subscribed_outgoing_webhooks_with_a_valid_signature(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);
        $webhook = $this->outgoingWebhook();

        $deliveries = app(WebhookDispatcher::class)->dispatch(
            'whatsapp.message.received',
            ['from' => '+15551234567', 'text' => 'hi'],
            ['owner_type' => $webhook->owner_type, 'owner_id' => $webhook->owner_id],
        );

        $this->assertCount(1, $deliveries);
        $delivery = $deliveries->first()->fresh();
        $this->assertSame(WebhookDelivery::STATUS_DELIVERED, $delivery->status);
        $this->assertSame(1, $delivery->attempts);
        $this->assertNotNull($delivery->delivered_at);

        Http::assertSent(function ($request) use ($webhook, $delivery) {
            $timestamp = $request->header('X-Webhook-Timestamp')[0];
            $expected = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$request->body(), $webhook->secret);

            return $request->url() === $webhook->url
                && $request->header('X-Webhook-Event')[0] === 'whatsapp.message.received'
                && $request->header('X-Webhook-Delivery')[0] === $delivery->event_id
                && $request->header('X-Webhook-Signature')[0] === $expected;
        });
    }

    #[Test]
    public function it_skips_outgoing_webhooks_not_subscribed_to_the_event(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);
        $webhook = $this->outgoingWebhook(['subscribed_events' => ['whatsapp.message.status']]);

        $deliveries = app(WebhookDispatcher::class)->dispatch(
            'whatsapp.message.received',
            ['x' => 1],
            ['owner_type' => $webhook->owner_type, 'owner_id' => $webhook->owner_id],
        );

        $this->assertCount(0, $deliveries);
        Http::assertNothingSent();
    }

    #[Test]
    public function it_retries_then_fails_after_max_attempts_keeping_a_stable_delivery_id(): void
    {
        config(['webhooks.delivery.max_attempts' => 3, 'webhooks.delivery.backoff' => [0, 0, 0]]);
        Http::fake(['*' => Http::response('boom', 500)]);
        $webhook = $this->outgoingWebhook();

        $delivery = app(WebhookDispatcher::class)
            ->queue($webhook, 'whatsapp.message.received', ['x' => 1])
            ->fresh();

        $this->assertSame(WebhookDelivery::STATUS_FAILED, $delivery->status);
        $this->assertSame(3, $delivery->attempts);
        $this->assertSame(500, $delivery->response_status);

        $deliveryIds = collect(Http::recorded())
            ->map(fn ($pair) => $pair[0]->header('X-Webhook-Delivery')[0])
            ->unique();
        $this->assertCount(3, Http::recorded());
        $this->assertCount(1, $deliveryIds, 'delivery id must be stable across retries');
    }

    #[Test]
    public function it_auto_disables_a_webhook_after_too_many_consecutive_failures(): void
    {
        Event::fake([WebhookDisabled::class]);
        config(['webhooks.delivery.max_attempts' => 1, 'webhooks.auto_disable_after' => 1]);
        Http::fake(['*' => Http::response('boom', 500)]);
        $webhook = $this->outgoingWebhook();

        app(WebhookDispatcher::class)->queue($webhook, 'whatsapp.message.received', ['x' => 1]);

        $this->assertFalse($webhook->fresh()->is_active);
        Event::assertDispatched(WebhookDisabled::class);
    }

    #[Test]
    public function incoming_webhooks_get_a_token_and_ingress_url_and_no_secret(): void
    {
        $owner = $this->makeUser();
        $webhook = Webhook::create([
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => $owner->id,
            'name' => 'Inbound',
            'url' => null,
        ]);

        $this->assertSame(Webhook::DIRECTION_INCOMING, $webhook->direction);
        $this->assertNotEmpty($webhook->token);
        $this->assertStringContainsString('/webhooks/ingress/', $webhook->url);
        $this->assertNull($webhook->secret);
    }

    #[Test]
    public function an_owner_can_trigger_its_webhooks_via_the_trait(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);
        $owner = $this->makeUser();
        $this->outgoingWebhook(['owner' => $owner]);

        $deliveries = $owner->triggerWebhook('whatsapp.message.received', ['x' => 1]);

        $this->assertCount(1, $deliveries);
        $this->assertCount(1, $owner->webhooks);
        $this->assertSame(WebhookDelivery::STATUS_DELIVERED, $deliveries->first()->fresh()->status);
    }

    #[Test]
    public function it_can_redeliver_a_previous_delivery(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);
        $webhook = $this->outgoingWebhook();

        $original = WebhookDelivery::create([
            'webhook_id' => $webhook->id,
            'event' => 'whatsapp.message.received',
            'event_id' => 'prior-id',
            'payload' => ['x' => 1],
            'status' => WebhookDelivery::STATUS_FAILED,
        ]);

        $new = app(WebhookDispatcher::class)->redeliver($original);

        $this->assertNotNull($new);
        $this->assertNotSame($original->event_id, $new->event_id);
        $this->assertSame(WebhookDelivery::STATUS_DELIVERED, $new->fresh()->status);
        $this->assertSame(2, WebhookDelivery::count());
    }

    #[Test]
    public function retry_stuck_command_reenqueues_due_deliveries(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);
        $webhook = $this->outgoingWebhook();

        $delivery = WebhookDelivery::create([
            'webhook_id' => $webhook->id,
            'event' => 'whatsapp.message.received',
            'event_id' => 'stuck-id',
            'payload' => ['x' => 1],
            'status' => WebhookDelivery::STATUS_PENDING,
            'next_attempt_at' => now()->subMinutes(5),
        ]);

        $this->artisan('webhooks:retry-stuck')->assertSuccessful();

        $this->assertSame(WebhookDelivery::STATUS_DELIVERED, $delivery->fresh()->status);
    }
}
