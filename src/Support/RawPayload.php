<?php

namespace Whilesmart\Webhooks\Support;

class RawPayload
{
    public static function encode(string $body): array
    {
        $isUtf8 = preg_match('//u', $body) === 1;

        return [
            'raw_body' => $isUtf8 ? $body : base64_encode($body),
            'encoding' => $isUtf8 ? 'utf-8' : 'base64',
        ];
    }

    public static function decode(array $payload): ?string
    {
        $body = $payload['raw_body'] ?? null;
        $encoding = $payload['encoding'] ?? null;

        if (! is_string($body)) {
            return null;
        }

        if ($encoding === 'utf-8') {
            return $body;
        }

        if ($encoding !== 'base64') {
            return null;
        }

        $decoded = base64_decode($body, true);

        return $decoded === false ? null : $decoded;
    }
}
