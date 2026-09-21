<?php

namespace Edos\ReverbMarketplace\Tests;

use Edos\ReverbMarketplace\ReverbClient;
use Edos\ReverbMarketplace\ReverbMarketplaceServiceProvider;
use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        // No test may reach Reverb: an unfaked request fails the test.
        Http::preventStrayRequests();
    }

    protected function getPackageProviders($app): array
    {
        return [ReverbMarketplaceServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('reverb-marketplace.token', 'test-token');
        $app['config']->set('reverb-marketplace.environment', 'sandbox');
        $app['config']->set('reverb-marketplace.retry_delay_ms', 0);
        $app['config']->set('reverb-marketplace.webhooks.token', 'hook-secret');
    }

    protected function client(): ReverbClient
    {
        return $this->app->make(ReverbClient::class);
    }
}
