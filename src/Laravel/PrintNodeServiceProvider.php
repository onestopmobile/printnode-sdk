<?php

declare(strict_types=1);

namespace OneStopMobile\PrintNodeSdk\Laravel;

use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\ServiceProvider;
use OneStopMobile\PrintNodeSdk\Printing\PrintManager;
use OneStopMobile\PrintNodeSdk\PrintNodeConfig;
use OneStopMobile\PrintNodeSdk\PrintNodeSdk;
use Override;
use RuntimeException;

final class PrintNodeServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/printnode.php', 'printnode');

        $this->app->singleton(LaravelPrintNodeConfigFactory::class);

        $this->app->singleton(PrintNodeConfig::class, fn (Container $app): PrintNodeConfig => $app->make(LaravelPrintNodeConfigFactory::class)
            ->makePrintNodeConfig($this->configRepository($app)));

        $this->app->singleton(PrintNodeSdk::class, static fn (Container $app): PrintNodeSdk => new PrintNodeSdk($app->make(PrintNodeConfig::class)));

        $this->app->singleton(PrintManager::class, fn (Container $app): PrintManager => $app->make(PrintNodeSdk::class)->printingWithConfig(
            $app->make(LaravelPrintNodeConfigFactory::class)
                ->makePrintManagerConfig($this->configRepository($app), $app),
        ));
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../../config/printnode.php' => $this->app->configPath('printnode.php'),
        ], 'printnode-config');
    }

    private function configRepository(Container $app): ConfigRepository
    {
        $repository = $app->make('config');

        if ($repository instanceof ConfigRepository) {
            return $repository;
        }

        throw new RuntimeException('Laravel config repository is not available.');
    }
}
