<?php

declare(strict_types=1);

namespace Beziworld\WFirma;

use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the wFirma client.
 */
final class WFirmaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/wfirma.php', 'wfirma');

        $this->app->singleton(WFirmaConfig::class, static function (Application $app): WFirmaConfig {
            /** @var array<string, mixed> $config */
            $config = $app->make(Config::class)->get('wfirma', []);

            return WFirmaConfig::fromArray($config);
        });

        $this->app->singleton(WFirmaClient::class, static fn(Application $app): WFirmaClient => new WFirmaClient(
            $app->make(HttpFactory::class),
            $app->make(WFirmaConfig::class),
        ));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__ . '/../config/wfirma.php' => $this->app->configPath('wfirma.php')], 'wfirma-config');
        }
    }
}
