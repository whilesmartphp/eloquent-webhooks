<?php

namespace Whilesmart\UserAuthentication\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\Attributes\WithMigration;

use function Orchestra\Testbench\workbench_path;

#[WithMigration]
abstract class TestCase extends \Orchestra\Testbench\TestCase
{
    use RefreshDatabase;

    /**
     * Define database migrations.
     */
    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(
            workbench_path('database/migrations')
        );
    }

    /**
     * Get package providers.
     */
    protected function getPackageProviders($app)
    {
        return [
            \Whilesmart\Webhooks\WebhooksServiceProvider::class];
    }
}
