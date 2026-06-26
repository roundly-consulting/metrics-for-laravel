<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Commands;

use Illuminate\Console\Command;
use RoundlyConsulting\Metrics\MetricsManager;

final class ListMetricsCommand extends Command
{
    protected $signature = 'metrics:list';

    protected $description = 'List every registered metric key.';

    public function handle(MetricsManager $manager): int
    {
        $keys = $manager->keys();

        if ($keys === []) {
            $this->warn('No metrics are registered.');

            return self::SUCCESS;
        }

        $this->table(
            ['Key', 'Metric'],
            array_map(
                fn (string $key): array => [$key, $manager->get($key)::class],
                $keys,
            ),
        );

        return self::SUCCESS;
    }
}
