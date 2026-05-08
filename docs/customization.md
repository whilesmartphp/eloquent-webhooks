# Customization

Eloquent Webhooks provides a configuration file that allows you to customize the package to fit your application's architecture.

## Configuration File

If you haven't already published the configuration file, you can do so using artisan:

```bash
php artisan vendor:publish --tag="webhooks-config"
```

This publishes the `config/webhooks.php` file, which includes the following customizable sections:

### Model Configuration

You can swap out the default models for your own by updating the `config/webhooks.php` file or using environment variables:

```php
'user_model' => env('WEBHOOKS_USER_MODEL', 'App\\Models\\User'),
'workspace_model' => env('WEBHOOKS_WORKSPACE_MODEL', 'App\\Models\\Workspace'),
'project_model' => env('WEBHOOKS_PROJECT_MODEL', 'App\\Models\\Project'),
```

### Route Configuration

The package registers API routes automatically. You can customize the route prefix, middleware, or disable route registration entirely:

```php
'register_routes' => env('WEBHOOKS_REGISTER_ROUTES', true),
'route_prefix' => env('WEBHOOKS_ROUTE_PREFIX', ''),
'route_middleware' => ['auth:sanctum'],
```

### Feature Flags

You can enable or disable built-in support for Workspace scoping, Project scoping, and event tracking based on your needs:

```php
'workspace_scoped' => env('WEBHOOKS_WORKSPACE_SCOPED', true),
'project_scoped' => env('WEBHOOKS_PROJECT_SCOPED', true),
'track_events' => env('WEBHOOKS_TRACK_EVENTS', true),
```

### Activity Logging Integration

If the `whilesmart/activities` package is installed, Eloquent Webhooks automatically detects it and logs an activity whenever a webhook event is processed.
