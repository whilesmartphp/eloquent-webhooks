# Eloquent Webhooks

[![Latest Version on Packagist](https://img.shields.io/packagist/v/whilesmart/webhooks.svg?style=flat-square)](https://packagist.org/packages/whilesmart/webhooks)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/whilesmart/eloquent-webhooks/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/whilesmart/eloquent-webhooks/actions?query=workflow%3Atests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/whilesmart/webhooks.svg?style=flat-square)](https://packagist.org/packages/whilesmart/webhooks)

A comprehensive webhook management package for Laravel applications. Easily manage, track, and process incoming webhooks with built-in support for workspace and project scoping.

## Features

- **Webhook Management:** Complete CRUD operations for webhooks.
- **Secure Ingress:** Automatic token generation for secure, unique webhook endpoints.
- **Signed Outbound Delivery:** Queued, retried, HMAC-signed delivery of events to customer URLs.
- **Polymorphic Ownership:** Any model (team, workspace, user, ...) can own a webhook.
- **Event Tracking:** Logs all incoming webhook payloads, headers, and processing status.
- **API Ready:** Comes with pre-configured controllers and routes for rapid development.

## Installation

You can install the package via composer:

```bash
composer require whilesmart/eloquent-webhooks
```

You should publish and run the migrations with:

```bash
php artisan vendor:publish --tag="webhooks-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="webhooks-config"
```

This is the contents of the published config file:

```php
return [
    // Use UUID primary keys instead of auto-incrementing integers.
    'uuids' => (bool) env('WEBHOOKS_UUIDS', false),

    'register_routes' => env('WEBHOOKS_REGISTER_ROUTES', true),
    'route_prefix' => env('WEBHOOKS_ROUTE_PREFIX', ''),
    'route_middleware' => ['auth:sanctum'],

    // Signed, retried outbound delivery.
    'signing' => [
        'header' => env('WEBHOOKS_SIGNATURE_HEADER', 'X-Webhook-Signature'),
        'algo' => env('WEBHOOKS_SIGNATURE_ALGO', 'sha256'),
    ],
    'delivery' => [
        'timeout' => (int) env('WEBHOOKS_DELIVERY_TIMEOUT', 10),
        'max_attempts' => (int) env('WEBHOOKS_DELIVERY_MAX_ATTEMPTS', 6),
        'backoff' => [10, 60, 300, 1800, 7200, 21600],
        'queue' => env('WEBHOOKS_DELIVERY_QUEUE', null),
        'connection' => env('WEBHOOKS_DELIVERY_CONNECTION', null),
    ],
    'auto_disable_after' => (int) env('WEBHOOKS_AUTO_DISABLE_AFTER', 15),
];
```

## Usage

### Managing Webhooks

The package provides a `Webhook` model that you can use to manage your webhooks.

```php
use Whilesmart\Webhooks\Models\Webhook;

$webhook = Webhook::create([
    'name' => 'My Webhook',
    'owner_type' => $team->getMorphClass(), // any model may own a webhook
    'owner_id' => $team->id,
    'created_by_type' => $user->getMorphClass(), // optional creator audit
    'created_by_id' => $user->id,
    'is_active' => true,
]);

// Get the unique ingress URL
echo $webhook->url; // https://your-app.com/webhooks/ingress/{token}
```

### Webhook Ingress

Incoming webhooks are sent to a unique URL containing a secure token. When a webhook is triggered:
1. The token is validated.
2. The `trigger_count` and `last_triggered_at` fields are updated.
3. A `WebhookEvent` is recorded containing the payload and headers.
4. If `whilesmart/activities` is installed, an activity log is automatically created.

### Outbound Webhooks

Outgoing webhooks deliver your application's events to a customer-supplied URL. Create one with `direction` set to `outgoing`, the destination `url`, and the `subscribed_events` it should receive (omit `subscribed_events` to receive all):

```php
$webhook = Webhook::create([
    'user_id' => $user->id,                 // creator (audit)
    'owner_type' => $team->getMorphClass(), // the owner that scopes the webhook
    'owner_id' => $team->id,
    'direction' => Webhook::DIRECTION_OUTGOING,
    'url' => 'https://customer.example.com/hooks',
    'subscribed_events' => ['whatsapp.message.received', 'whatsapp.message.status'],
]);
// $webhook->secret is generated automatically and used to sign deliveries.
```

Fire an event to every matching active webhook:

```php
app(WebhookDispatcher::class)->dispatch(
    'whatsapp.message.received',
    ['from' => '+15551234567', 'text' => 'hi'],
    ['owner_type' => $team->getMorphClass(), 'owner_id' => $team->id],
);

// Or, on a model using the HasWebhooks trait:
$team->triggerWebhook('whatsapp.message.received', $payload);
```

Each match creates a `WebhookDelivery` and queues a `DeliverWebhook` job that POSTs the JSON payload with these headers:

- `X-Webhook-Id`, `X-Webhook-Event`, `X-Webhook-Delivery` (stable id for deduplication), `X-Webhook-Timestamp`
- `X-Webhook-Signature: sha256=<hmac>` where the HMAC is `hash_hmac('sha256', "{timestamp}.{body}", secret)` (header name and algorithm configurable)

Non-2xx responses, timeouts, and connection errors are retried with exponential backoff up to `delivery.max_attempts`; after `auto_disable_after` consecutive failures the webhook is deactivated and a `WebhookDisabled` event is emitted. Run `php artisan webhooks:retry-stuck` (e.g. on a schedule) to re-enqueue any deliveries whose retry is due. Tune everything under the `delivery`, `signing`, and `auto_disable_after` keys in `config/webhooks.php`.

### API Endpoints

By default, the package registers the following routes (protected by `auth:sanctum`):

#### Management Routes
- `GET /webhooks`: List all webhooks for the authenticated user.
- `POST /webhooks`: Create a new webhook.
- `GET /webhooks/{id}`: Get webhook details.
- `PATCH /webhooks/{id}`: Update a webhook (or regenerate its token).
- `DELETE /webhooks/{id}`: Soft delete a webhook.
- `GET /webhooks/{id}/events`: List event history for a webhook.

The built-in CRUD routes scope webhooks to the authenticated user as owner. To manage webhooks
owned by another model (a team, workspace, etc.), set the `owner` morph directly and authorize
access with your own policy.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.


## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
