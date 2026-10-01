<?php

namespace Whilesmart\Webhooks\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Whilesmart\Webhooks\Support\RawPayload;

class RawPayloadTest extends TestCase
{
    #[Test]
    public function bodies_survive_json_storage_without_changing_their_signature(): void
    {
        $bodies = ['{"zeta":1,  "alpha":{"nested":true}}', "Text with café\n", "\xFF\x00\x80", ''];

        foreach ($bodies as $body) {
            $stored = json_encode(RawPayload::encode($body), JSON_THROW_ON_ERROR);
            $restored = RawPayload::decode(json_decode($stored, true, 512, JSON_THROW_ON_ERROR));

            $this->assertSame($body, $restored);
            $this->assertSame(hash_hmac('sha256', $body, 'test-secret'), hash_hmac('sha256', $restored, 'test-secret'));
        }
    }

    #[Test]
    public function ordinary_payloads_and_invalid_envelopes_are_not_decoded(): void
    {
        foreach ([['action' => 'opened'], ['raw_body' => 'text'], ['raw_body' => [], 'encoding' => 'utf-8'],
            ['raw_body' => 'text', 'encoding' => 'unknown'], ['raw_body' => '!!!', 'encoding' => 'base64']] as $payload) {
            $this->assertNull(RawPayload::decode($payload));
        }
    }
}
