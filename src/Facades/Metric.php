<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Facades;

use Illuminate\Support\Facades\Facade;
use RoundlyConsulting\Metrics\Metrics;
use RoundlyConsulting\Metrics\MetricsManager;
use RoundlyConsulting\Metrics\Types\Partition\PendingPartition;
use RoundlyConsulting\Metrics\Types\Progress\PendingProgress;
use RoundlyConsulting\Metrics\Types\Trend\PendingTrend;
use RoundlyConsulting\Metrics\Types\Value\PendingValue;

/**
 * @method static MetricsManager register(string $key, string|Metrics|\Closure $metric)
 * @method static Metrics get(string $key)
 * @method static bool has(string $key)
 * @method static array<string, Metrics> all()
 * @method static PendingValue value()
 * @method static PendingTrend trend()
 * @method static PendingProgress progress()
 * @method static PendingPartition partition()
 *
 * @see MetricsManager
 */
final class Metric extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return MetricsManager::class;
    }
}
