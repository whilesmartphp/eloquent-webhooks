<?php

namespace Whilesmart\Webhooks\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\Attributes\WithMigration;

#[WithMigration]
abstract class TestCase extends \Orchestra\Testbench\TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app)
    {
        return [
            \Whilesmart\Webhooks\WebhooksServiceProvider::class,
        ];
    }
}
