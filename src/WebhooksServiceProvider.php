<?php

namespace Whilesmart\Webhooks;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Whilesmart\Webhooks\Console\RetryStuckDeliveries;

class WebhooksServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/webhooks.php', 'webhooks');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->publishes([
            __DIR__ . '/../config/webhooks.php' => config_path('webhooks.php'),
        ], 'webhooks-config');

        if (config('webhooks.register_routes', true)) {
            $this->registerRoutes();
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                RetryStuckDeliveries::class,
            ]);
        }
    }

    protected function registerRoutes(): void
    {
        Route::group([
            'prefix' => config('webhooks.route_prefix', 'api'),
            'middleware' => config('webhooks.route_middleware', ['auth:sanctum']),
        ], function () {
            $this->loadRoutesFrom(__DIR__ . '/../routes/webhooks.php');
        });
    }
}
