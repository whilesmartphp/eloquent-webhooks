## [1.0.0] - 2026-06-16
- Incoming webhook management: token-protected ingress URLs with payload and header logging. The incoming address is registered by the package, unauthenticated, since the sender holds only the token in the URL.
- Incoming deliveries keep their original body in a JSON envelope. `rawBody()` restores the original bytes for signature checks; `decoded()` reads the body as data.
- Signed, retried outbound webhook delivery: outgoing webhooks POST to a customer URL with an HMAC signature, delivered through a queued job with exponential-backoff retries.
- Each delivery attempt is recorded with a stable delivery id for consumer-side deduplication; endpoints are auto-disabled after too many consecutive failures.
- Webhooks are owned through a polymorphic `owner` relation so any model can own one, with `user_id` retained as the creator.
- Includes the `WebhookDispatcher` service, `HasWebhooks` trait, `WebhookDelivered` / `WebhookDeliveryFailed` / `WebhookDisabled` events, and a `webhooks:retry-stuck` command.
