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
    public function an_incoming_delivery_keeps_the_bytes_it_arrived_as(): void
    {
        $webhook = $this->incomingWebhook();

        // Key order and spacing are part of what a signature covers, so the
        // stored copy has to be the sender's bytes rather than a re-encoding.
        $raw = '{"zeta":1,  "alpha":{"nested":true}}';

        $this->call(
            'POST',
            "/webhooks/ingress/{$webhook->token}",
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            $raw,
        )->assertStatus(202);

        $event = WebhookEvent::firstOrFail();

        $this->assertSame($raw, $event->raw_payload);
        $this->assertSame(hash_hmac('sha256', $raw, 'a-secret'), hash_hmac('sha256', $event->raw_payload, 'a-secret'));
    }

    #[Test]
    public function the_parsed_payload_is_still_there_to_read(): void
    {
        $webhook = $this->incomingWebhook();

        $this->call(
            'POST',
            "/webhooks/ingress/{$webhook->token}",
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            '{"action":"opened"}',
        )->assertStatus(202);

        $this->assertSame('opened', WebhookEvent::firstOrFail()->payload['action']);
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
