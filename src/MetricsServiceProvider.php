<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics;

use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Metrics\Commands\ListMetricsCommand;
use RoundlyConsulting\Metrics\Commands\ShowMetricCommand;

final class MetricsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/metrics.php', 'metrics');

        $this->app->singleton(MetricsManager::class);
        $this->app->alias(MetricsManager::class, 'metrics');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ListMetricsCommand::class,
                ShowMetricCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/metrics.php' => config_path('metrics.php'),
            ], 'metrics-config');
        }
    }
}
