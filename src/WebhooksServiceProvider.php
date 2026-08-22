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

        // The address an incoming webhook is told to post to. It cannot sit
        // behind the authenticated stack, because the sender is a third party
        // holding nothing but the token in the URL. Every consumer was
        // registering this by hand or the URL the model hands out answered 404.
        Route::group([
            'prefix' => config('webhooks.route_prefix', 'api'),
            'middleware' => config('webhooks.ingress_middleware', []),
        ], function () {
            $this->loadRoutesFrom(__DIR__ . '/../routes/ingress.php');
        });
    }
}
