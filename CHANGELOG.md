## [1.0.0] - 2026-06-16
- Incoming webhook management: token-protected ingress URLs with payload and header logging.
- Signed, retried outbound webhook delivery: outgoing webhooks POST to a customer URL with an HMAC signature, delivered through a queued job with exponential-backoff retries.
- Each delivery attempt is recorded with a stable delivery id for consumer-side deduplication; endpoints are auto-disabled after too many consecutive failures.
- Webhooks are owned through a polymorphic `owner` relation so any model can own one, with `user_id` retained as the creator.
- Includes the `WebhookDispatcher` service, `HasWebhooks` trait, `WebhookDelivered` / `WebhookDeliveryFailed` / `WebhookDisabled` events, and a `webhooks:retry-stuck` command.
