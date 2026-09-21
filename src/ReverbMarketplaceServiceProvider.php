<?php

namespace Edos\ReverbMarketplace;

use Edos\ReverbMarketplace\Console\CheckCommand;
use Edos\ReverbMarketplace\Webhooks\VerifyWebhookToken;
use Edos\ReverbMarketplace\Webhooks\WebhookController;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ReverbMarketplaceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/reverb-marketplace.php', 'reverb-marketplace');

        // Not a singleton of state: the config is read when the client is
        // first resolved, and usingToken() / usingEnvironment() hand back
        // copies, so one shared instance is safe under Octane.
        $this->app->singleton(ReverbClient::class, fn (Application $app): ReverbClient => new ReverbClient(
            $app->make(Factory::class),
            (array) $app['config']->get('reverb-marketplace', []),
            $app->bound('cache') ? $app->make(CacheRepository::class) : null,
        ));

        $this->app->alias(ReverbClient::class, 'reverb-marketplace');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/reverb-marketplace.php' => config_path('reverb-marketplace.php'),
            ], 'reverb-marketplace-config');

            $this->commands([CheckCommand::class]);
        }

        $this->registerWebhookRoute();
    }

    protected function registerWebhookRoute(): void
    {
        $path = config('reverb-marketplace.webhooks.path');

        if (blank($path) || $this->app->routesAreCached()) {
            return;
        }

        Route::post($path, WebhookController::class)
            ->middleware([...(array) config('reverb-marketplace.webhooks.middleware', []), VerifyWebhookToken::class])
            ->name('reverb-marketplace.webhook');
    }
}
