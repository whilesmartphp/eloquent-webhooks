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
