<?php

namespace Whilesmart\Webhooks\Tests\Feature;

use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Whilesmart\Webhooks\Models\Webhook;
use Whilesmart\Webhooks\Tests\TestCase;
use Workbench\App\Models\User;

class WebhookCrudTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [
            \Laravel\Sanctum\SanctumServiceProvider::class,
            \Whilesmart\Webhooks\WebhooksServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app)
    {
        $app['config']->set('auth.providers.users.model', User::class);
        $app['config']->set('auth.guards.sanctum', ['driver' => 'sanctum', 'provider' => 'users']);
    }

    private function user(): User
    {
        return User::forceCreate([
            'name' => 'Dev',
            'email' => 'dev+'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
        ]);
    }

    #[Test]
    public function a_user_can_create_a_webhook_with_provider_metadata(): void
    {
        $user = $this->user();
        Sanctum::actingAs($user);

        $response = $this->postJson('/webhooks', [
            'name' => 'My hook',
            'provider' => 'stripe',
            'event_type' => 'payment.succeeded',
        ]);

        $response->assertStatus(201)->assertJsonPath('data.name', 'My hook');

        $this->assertDatabaseHas('webhooks', [
            'name' => 'My hook',
            'owner_type' => User::class,
            'owner_id' => $user->id,
            'created_by_type' => User::class,
            'created_by_id' => $user->id,
        ]);

        $webhook = Webhook::first();
        $this->assertSame('stripe', $webhook->metadata['provider']);
        $this->assertSame('payment.succeeded', $webhook->metadata['event_type']);
        $this->assertSame(Webhook::DIRECTION_INCOMING, $webhook->direction);
        $this->assertNotNull($webhook->token);
    }

    #[Test]
    public function a_user_only_lists_webhooks_they_own(): void
    {
        $alice = $this->user();
        $bob = $this->user();

        Sanctum::actingAs($alice);
        $this->postJson('/webhooks', ['name' => 'Alice hook', 'provider' => 'custom'])->assertStatus(201);

        Sanctum::actingAs($alice);
        $this->getJson('/webhooks')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.provider', 'custom');

        Sanctum::actingAs($bob);
        $this->getJson('/webhooks')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }
}
