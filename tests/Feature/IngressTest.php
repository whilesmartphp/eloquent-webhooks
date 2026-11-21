<?php

namespace Whilesmart\Webhooks\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Whilesmart\Webhooks\Models\Webhook;
use Whilesmart\Webhooks\Models\WebhookEvent;
use Whilesmart\Webhooks\Tests\TestCase;
use Workbench\App\Models\User;

class IngressTest extends TestCase
{
    #[Test]
    public function incoming_json_keeps_application_fields_at_the_top_level(): void
    {
        $webhook = $this->incomingWebhook();
        $payload = ['action' => 'opened', 'raw_body' => 'application value', 'encoding' => 'application encoding'];

        $this->postJson("/webhooks/ingress/{$webhook->token}?source=customer", $payload)->assertStatus(202);

        $event = WebhookEvent::firstOrFail();

        $this->assertEquals($payload + ['source' => 'customer'], $event->payload);
        $this->assertEquals($event->payload, $event->toArray()['payload']);
    }

    #[Test]
    public function incoming_forms_keep_application_fields_at_the_top_level(): void
    {
        $webhook = $this->incomingWebhook();

        $this->post("/webhooks/ingress/{$webhook->token}", ['action' => 'opened', 'number' => '7'])
            ->assertStatus(202);

        $this->assertEquals(['action' => 'opened', 'number' => '7'], WebhookEvent::firstOrFail()->payload);
    }

    #[Test]
    public function a_repeated_delivery_is_acknowledged_without_being_recorded_again(): void
    {
        config(['webhooks.delivery_id_header' => 'X-GitHub-Delivery']);
        $webhook = $this->incomingWebhook();
        $headers = ['X-GitHub-Delivery' => 'delivery-1'];

        $this->postJson("/webhooks/ingress/{$webhook->token}", ['action' => 'opened'], $headers)->assertStatus(202);
        $this->postJson("/webhooks/ingress/{$webhook->token}", ['action' => 'opened'], $headers)
            ->assertStatus(200)
            ->assertJson(['message' => 'Webhook already received.']);

        $this->assertSame(1, WebhookEvent::count());
        $this->assertSame('delivery-1', WebhookEvent::firstOrFail()->delivery_id);
        $this->assertSame(1, $webhook->fresh()->trigger_count);
    }

    #[Test]
    public function distinct_deliveries_are_each_recorded(): void
    {
        config(['webhooks.delivery_id_header' => 'X-GitHub-Delivery']);
        $webhook = $this->incomingWebhook();

        $this->postJson("/webhooks/ingress/{$webhook->token}", ['n' => 1], ['X-GitHub-Delivery' => 'delivery-1'])->assertStatus(202);
        $this->postJson("/webhooks/ingress/{$webhook->token}", ['n' => 2], ['X-GitHub-Delivery' => 'delivery-2'])->assertStatus(202);

        $this->assertSame(2, WebhookEvent::count());
    }

    #[Test]
    public function without_a_delivery_header_every_request_is_recorded(): void
    {
        $webhook = $this->incomingWebhook();
        $headers = ['X-GitHub-Delivery' => 'delivery-1'];

        $this->postJson("/webhooks/ingress/{$webhook->token}", ['n' => 1], $headers)->assertStatus(202);
        $this->postJson("/webhooks/ingress/{$webhook->token}", ['n' => 1], $headers)->assertStatus(202);

        $this->assertSame(2, WebhookEvent::count());
    }

    #[Test]
    public function a_repeated_delivery_leaves_the_callers_transaction_usable(): void
    {
        $webhook = $this->incomingWebhook();

        \Illuminate\Support\Facades\DB::transaction(function () use ($webhook) {
            $this->assertNotNull($webhook->receive('delivery-1', ['payload' => ['n' => 1]]));
            $this->assertNull($webhook->receive('delivery-1', ['payload' => ['n' => 1]]));
            $this->assertNotNull($webhook->receive('delivery-2', ['payload' => ['n' => 2]]));
        });

        $this->assertSame(2, WebhookEvent::count());
    }

    private function incomingWebhook(): Webhook
    {
        $user = User::forceCreate([
            'name' => 'Owner',
            'email' => 'owner+'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
        ]);

        return Webhook::create([
            'owner_type' => $user->getMorphClass(),
            'owner_id' => $user->getKey(),
            'name' => 'Inbound',
            'direction' => Webhook::DIRECTION_INCOMING,
            'is_active' => true,
        ]);
    }
}
