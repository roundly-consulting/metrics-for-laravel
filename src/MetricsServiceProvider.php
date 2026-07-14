<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics;

use RoundlyConsulting\Metrics\Commands\ListMetricsCommand;
use RoundlyConsulting\Metrics\Commands\ShowMetricCommand;
use RoundlyConsulting\PackageToolkit\Package;
use RoundlyConsulting\PackageToolkit\PackageServiceProvider;

final class MetricsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('metrics')
            ->hasConfigFile()
            ->hasCommands([
                ListMetricsCommand::class,
                ShowMetricCommand::class,
            ])
            ->contributesToAbout(static function (): array {
                $range = config('metrics.default_range', 'ALL');

                return [
                    'Registered metrics' => (string) count(app(MetricsManager::class)->keys()),
                    'Result cache' => config('metrics.cache.enabled') === true ? 'ENABLED' : 'OFF',
                    'Default range' => is_string($range) ? $range : 'ALL',
                ];
            });
    }

    public function register(): void
    {
        parent::register();

        $this->app->singleton(MetricsManager::class);
        $this->app->alias(MetricsManager::class, 'metrics');
    }
}
