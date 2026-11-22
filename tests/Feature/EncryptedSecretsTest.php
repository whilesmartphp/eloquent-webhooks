<?php

namespace Whilesmart\Webhooks\Tests\Feature;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Whilesmart\Webhooks\Models\Webhook;
use Whilesmart\Webhooks\Services\WebhookDispatcher;
use Whilesmart\Webhooks\Tests\TestCase;
use Workbench\App\Models\User;

class EncryptedSecretsTest extends TestCase
{
    protected function defineEnvironment($app)
    {
        $app['config']->set('queue.default', 'sync');
    }

    private function outgoingWebhook(array $overrides = []): Webhook
    {
        $owner = User::forceCreate([
            'name' => 'Owner',
            'email' => 'owner+'.uniqid().'@example.com',
            'password' => bcrypt('password'),
        ]);

        return Webhook::create(array_merge([
            'owner_type' => $owner->getMorphClass(),
            'owner_id' => $owner->id,
            'name' => 'Customer endpoint',
            'direction' => Webhook::DIRECTION_OUTGOING,
            'url' => 'https://customer.example.com/hooks',
            'subscribed_events' => ['order.created'],
            'is_active' => true,
        ], $overrides));
    }

    private function rawSecret(Webhook $webhook): ?string
    {
        return DB::table('webhooks')->where('id', $webhook->id)->value('secret');
    }

    private function encryptSecrets(bool $enabled): void
    {
        config(['webhooks.encrypt_secrets' => $enabled]);
    }

    #[Test]
    public function the_secret_column_can_hold_ciphertext(): void
    {
        $this->assertSame('text', Schema::getColumnType('webhooks', 'secret'));
    }

    #[Test]
    public function a_provided_secret_is_stored_encrypted_and_read_back_plain(): void
    {
        $this->encryptSecrets(true);

        $webhook = $this->outgoingWebhook(['secret' => 'plain-secret']);
        $raw = $this->rawSecret($webhook);

        $this->assertNotSame('plain-secret', $raw);
        $this->assertSame('plain-secret', Crypt::decryptString($raw));
        $this->assertSame('plain-secret', Webhook::find($webhook->id)->secret);
    }

    #[Test]
    public function a_generated_secret_is_stored_encrypted(): void
    {
        $this->encryptSecrets(true);

        $webhook = $this->outgoingWebhook();
        $plain = Webhook::find($webhook->id)->secret;

        $this->assertSame(40, strlen($plain));
        $this->assertSame($plain, Crypt::decryptString($this->rawSecret($webhook)));
    }

    #[Test]
    public function deliveries_are_signed_with_the_plain_secret_when_encrypted(): void
    {
        $this->encryptSecrets(true);
        Http::fake(['*' => Http::response('ok', 200)]);
        $webhook = $this->outgoingWebhook(['secret' => 'plain-secret']);

        app(WebhookDispatcher::class)->dispatch(
            'order.created',
            ['id' => 1],
            ['owner_type' => $webhook->owner_type, 'owner_id' => $webhook->owner_id],
        );

        Http::assertSentCount(1);
        Http::assertSent(function ($request) {
            $timestamp = $request->header('X-Webhook-Timestamp')[0];
            $expected = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$request->body(), 'plain-secret');

            return $request->header('X-Webhook-Signature')[0] === $expected;
        });
    }

    #[Test]
    public function the_secret_is_hidden_from_serialization_when_encrypted(): void
    {
        $this->encryptSecrets(true);

        $webhook = $this->outgoingWebhook(['secret' => 'plain-secret']);

        $this->assertArrayNotHasKey('secret', $webhook->fresh()->toArray());
        $this->assertSame('plain-secret', $webhook->fresh()->makeVisible('secret')->toArray()['secret']);
    }

    #[Test]
    public function disabled_mode_keeps_plain_secrets_and_serialization(): void
    {
        $this->encryptSecrets(false);

        $webhook = $this->outgoingWebhook(['secret' => 'plain-secret']);

        $this->assertSame('plain-secret', $this->rawSecret($webhook));
        $this->assertSame('plain-secret', $webhook->fresh()->secret);
        $this->assertSame('plain-secret', $webhook->fresh()->toArray()['secret']);
    }

    #[Test]
    public function the_command_encrypts_existing_plain_secrets_idempotently(): void
    {
        $this->encryptSecrets(false);
        $outgoing = $this->outgoingWebhook(['secret' => 'plain-secret']);
        $trashed = $this->outgoingWebhook(['secret' => 'trashed-secret']);
        $trashed->delete();
        $incoming = Webhook::create(['name' => 'Inbound']);

        $this->encryptSecrets(true);
        $this->artisan('webhooks:encrypt-secrets')
            ->expectsOutput('Encrypted 2 webhook secrets, skipped 0.')
            ->assertSuccessful();

        $ciphertext = $this->rawSecret($outgoing);
        $this->assertSame('plain-secret', Crypt::decryptString($ciphertext));
        $this->assertSame('trashed-secret', Crypt::decryptString($this->rawSecret($trashed)));
        $this->assertNull($this->rawSecret($incoming));
        $this->assertSame('plain-secret', Webhook::find($outgoing->id)->secret);

        $this->artisan('webhooks:encrypt-secrets')
            ->expectsOutput('Encrypted 0 webhook secrets, skipped 2.')
            ->assertSuccessful();

        $this->assertSame($ciphertext, $this->rawSecret($outgoing));
    }

    #[Test]
    public function the_command_decrypts_secrets_back_idempotently(): void
    {
        $this->encryptSecrets(true);
        $webhook = $this->outgoingWebhook(['secret' => 'plain-secret']);

        $this->encryptSecrets(false);
        $this->artisan('webhooks:encrypt-secrets', ['--decrypt' => true])
            ->expectsOutput('Decrypted 1 webhook secrets, skipped 0.')
            ->assertSuccessful();

        $this->assertSame('plain-secret', $this->rawSecret($webhook));

        $this->artisan('webhooks:encrypt-secrets', ['--decrypt' => true])
            ->expectsOutput('Decrypted 0 webhook secrets, skipped 1.')
            ->assertSuccessful();

        $this->assertSame('plain-secret', $this->rawSecret($webhook));
        $this->assertSame('plain-secret', Webhook::find($webhook->id)->secret);
    }

    #[Test]
    public function the_command_refuses_to_run_against_the_configured_mode(): void
    {
        $this->encryptSecrets(false);
        $webhook = $this->outgoingWebhook(['secret' => 'plain-secret']);

        $this->artisan('webhooks:encrypt-secrets')->assertFailed();
        $this->assertSame('plain-secret', $this->rawSecret($webhook));

        $this->encryptSecrets(true);
        $this->artisan('webhooks:encrypt-secrets', ['--decrypt' => true])->assertFailed();
        $this->assertSame('plain-secret', $this->rawSecret($webhook));
    }
}
