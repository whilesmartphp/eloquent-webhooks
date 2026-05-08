# Eloquent Webhooks

[![Latest Version on Packagist](https://img.shields.io/packagist/v/whilesmart/webhooks.svg?style=flat-square)](https://packagist.org/packages/whilesmart/webhooks)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/whilesmart/eloquent-webhooks/tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/whilesmart/eloquent-webhooks/actions?query=workflow%3Atests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/whilesmart/webhooks.svg?style=flat-square)](https://packagist.org/packages/whilesmart/webhooks)

A comprehensive webhook management package for Laravel applications. Easily manage, track, and process incoming webhooks with built-in support for workspace and project scoping.

## Features

- **Webhook Management:** Complete CRUD operations for webhooks.
- **Secure Ingress:** Automatic token generation for secure, unique webhook endpoints.
- **Event Tracking:** Logs all incoming webhook payloads, headers, and processing status.
- **Scoped by Default:** Built-in support for User, Workspace, and Project scoping.
- **Flexible Integration:** Seamlessly integrates with other WhileSmart packages like `eloquent-workspaces`, `projects`, and `activities`.
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
    /*
    |--------------------------------------------------------------------------
    | Model Configuration
    |--------------------------------------------------------------------------
    */
    'user_model' => env('WEBHOOKS_USER_MODEL', 'App\\Models\\User'),
    'workspace_model' => env('WEBHOOKS_WORKSPACE_MODEL', 'App\\Models\\Workspace'),
    'project_model' => env('WEBHOOKS_PROJECT_MODEL', 'App\\Models\\Project'),

    /*
    |--------------------------------------------------------------------------
    | Route Configuration
    |--------------------------------------------------------------------------
    */
    'register_routes' => env('WEBHOOKS_REGISTER_ROUTES', true),
    'route_prefix' => env('WEBHOOKS_ROUTE_PREFIX', ''),
    'route_middleware' => ['auth:sanctum'],

    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    */
    'workspace_scoped' => env('WEBHOOKS_WORKSPACE_SCOPED', true),
    'project_scoped' => env('WEBHOOKS_PROJECT_SCOPED', true),
    'track_events' => env('WEBHOOKS_TRACK_EVENTS', true),
];
```

## Usage

### Managing Webhooks

The package provides a `Webhook` model that you can use to manage your webhooks.

```php
use Whilesmart\Webhooks\Models\Webhook;

$webhook = Webhook::create([
    'name' => 'My Webhook',
    'user_id' => $user->id,
    'project_id' => $project->id,
    'workspace_id' => $workspace->id,
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

### API Endpoints

By default, the package registers the following routes (protected by `auth:sanctum`):

#### Management Routes
- `GET /webhooks`: List all webhooks for the authenticated user.
- `POST /webhooks`: Create a new webhook.
- `GET /webhooks/{id}`: Get webhook details.
- `PATCH /webhooks/{id}`: Update a webhook (or regenerate its token).
- `DELETE /webhooks/{id}`: Soft delete a webhook.
- `GET /webhooks/{id}/events`: List event history for a webhook.

#### Workspace-Scoped Routes
- `GET /workspaces/{workspaceId}/webhooks`
- `POST /workspaces/{workspaceId}/webhooks`
- ... (and other CRUD operations prefixed with workspace)


## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.


## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
