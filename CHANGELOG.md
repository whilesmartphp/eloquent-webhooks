## [Unreleased]
- Webhook secrets can be encrypted at rest with the application key by enabling `encrypt_secrets`. It is off by default. The `secret` column is widened to `text` to hold ciphertext, and `webhooks:encrypt-secrets` converts existing secrets in either direction.

## [1.0.0] - 2026-06-16
- Incoming webhook management: token-protected ingress URLs with payload and header logging. The incoming address is registered by the package, unauthenticated, since the sender holds only the token in the URL.
- Applications can use `RawPayload` to encode exact request bytes in JSON and decode them later. Incoming event payloads retain their existing parsed structure.
- Signed, retried outbound webhook delivery: outgoing webhooks POST to a customer URL with an HMAC signature, delivered through a queued job with exponential-backoff retries.
- Each delivery attempt is recorded with a stable delivery id for consumer-side deduplication; endpoints are auto-disabled after too many consecutive failures.
- Webhooks are owned through a polymorphic `owner` relation so any model can own one, with `user_id` retained as the creator.
- Includes the `WebhookDispatcher` service, `HasWebhooks` trait, `WebhookDelivered` / `WebhookDeliveryFailed` / `WebhookDisabled` events, and a `webhooks:retry-stuck` command.
