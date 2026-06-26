<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Commands;

use Illuminate\Console\Command;
use RoundlyConsulting\Metrics\MetricsManager;

final class ShowMetricCommand extends Command
{
    protected $signature = 'metrics:show {key : The registered metric key} {--range= : Override the metric range}';

    protected $description = 'Resolve a registered metric and print its envelope.';

    public function handle(MetricsManager $manager): int
    {
        $key = (string) $this->argument('key');

        if (! $manager->has($key)) {
            $this->error("No metric is registered under the [{$key}] key.");

            return self::FAILURE;
        }

        $metric = $manager->get($key);

        $range = $this->option('range');

        if (is_string($range) && $range !== '') {
            $metric->range($range);
        }

        $this->line((string) json_encode($metric->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }
}
