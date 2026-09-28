<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Facades;

use Closure;
use Illuminate\Support\Facades\Facade;
use RoundlyConsulting\Metrics\Dashboard;
use RoundlyConsulting\Metrics\Metric;
use RoundlyConsulting\Metrics\MetricsManager;
use RoundlyConsulting\Metrics\Testing\MetricsFake;
use RoundlyConsulting\Metrics\Types\Partition\PendingPartition;
use RoundlyConsulting\Metrics\Types\Progress\PendingProgress;
use RoundlyConsulting\Metrics\Types\Trend\PendingTrend;
use RoundlyConsulting\Metrics\Types\Value\PendingValue;

/**
 * @method static MetricsManager register(string $key, class-string<Metric>|Metric|Closure(): Metric $metric)
 * @method static MetricsManager unregister(string $key)
 * @method static Metric get(string $key)
 * @method static bool has(string $key)
 * @method static list<string> keys()
 * @method static array<string, Metric> all()
 * @method static Dashboard dashboard(array<array-key, string> $keys)
 * @method static PendingValue value()
 * @method static PendingTrend trend()
 * @method static PendingProgress progress()
 * @method static PendingPartition partition()
 * @method static void forget(string|Metric $metric)
 * @method static void flushCache()
 * @method static void assertResolved(string $key, ?int $times = null)
 * @method static void assertResolvedTimes(string $key, int $times)
 * @method static void assertNotResolved(string $key)
 * @method static void assertNothingResolved()
 * @method static void assertForgotten(string $key)
 * @method static void assertNotForgotten(string $key)
 * @method static void assertNothingForgotten()
 * @method static void assertCacheFlushed()
 * @method static void assertCacheNotFlushed()
 *
 * @see MetricsManager
 * @see MetricsFake
 */
final class Metrics extends Facade
{
    /**
     * Swap the manager for a recording {@see MetricsFake} — in the facade and in the
     * container, so injected managers get it too — and return it. The fake keeps every
     * registered metric and answers the given keys with canned results: a number becomes
     * a value result, an array the raw result, and a `Result` or `Metric` is used as-is.
     *
     * @param  array<string, mixed>  $results
     */
    public static function fake(array $results = []): MetricsFake
    {
        $container = app();
        $current = $container->make(MetricsManager::class);

        $fake = new MetricsFake($container, $results, $current);

        self::swap($fake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return MetricsManager::class;
    }
}
