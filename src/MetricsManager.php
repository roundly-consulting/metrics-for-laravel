<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics;

use Closure;
use Illuminate\Contracts\Container\Container;
use RoundlyConsulting\Metrics\Exceptions\UnknownMetricException;
use RoundlyConsulting\Metrics\Facades\Metrics;
use RoundlyConsulting\Metrics\Support\ResultCache;
use RoundlyConsulting\Metrics\Testing\MetricsFake;
use RoundlyConsulting\Metrics\Types\Partition\PendingPartition;
use RoundlyConsulting\Metrics\Types\Progress\PendingProgress;
use RoundlyConsulting\Metrics\Types\Trend\PendingTrend;
use RoundlyConsulting\Metrics\Types\Value\PendingValue;

/**
 * The root of the {@see Metrics} facade: the metric registry, the ad-hoc builders and
 * the result cache. Inject it to use the same API without the facade.
 *
 * Not final: {@see MetricsFake} extends it, so code that injects the manager receives
 * the fake under `Metrics::fake()`.
 */
class MetricsManager
{
    /**
     * @var array<string, class-string<Metric>|Metric|Closure(): Metric>
     */
    protected array $metrics = [];

    public function __construct(protected readonly Container $container) {}

    /**
     * Register a metric under a key: a class-string (resolved through the container), an
     * instance, or a closure that builds one.
     *
     * @param  class-string<Metric>|Metric|Closure(): Metric  $metric
     */
    public function register(string $key, string|Metric|Closure $metric): self
    {
        $this->metrics[$key] = $metric;

        return $this;
    }

    /**
     * Remove a metric from the registry. Removing a key that is not registered is a no-op.
     */
    public function unregister(string $key): self
    {
        unset($this->metrics[$key]);

        return $this;
    }

    /**
     * @throws UnknownMetricException
     */
    public function get(string $key): Metric
    {
        if (! array_key_exists($key, $this->metrics)) {
            throw UnknownMetricException::forKey($key);
        }

        return $this->resolve($this->metrics[$key])->withKey($key);
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->metrics);
    }

    /**
     * The keys of every registered metric, without resolving them.
     *
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->metrics);
    }

    /**
     * @return array<string, Metric>
     */
    public function all(): array
    {
        $resolved = [];

        foreach (array_keys($this->metrics) as $key) {
            $resolved[$key] = $this->get($key);
        }

        return $resolved;
    }

    /**
     * Resolve many registered metrics at once into one keyed envelope.
     *
     * @param  array<array-key, string>  $keys
     */
    public function dashboard(array $keys): Dashboard
    {
        return new Dashboard($this, array_values($keys));
    }

    public function value(): PendingValue
    {
        return new PendingValue;
    }

    public function trend(): PendingTrend
    {
        return new PendingTrend;
    }

    public function progress(): PendingProgress
    {
        return new PendingProgress;
    }

    public function partition(): PendingPartition
    {
        return new PendingPartition;
    }

    /**
     * Forget every cached result of one metric — every range, timezone and option it was
     * cached under. Pass a registry key, or a metric instance for an unregistered one (an
     * ad-hoc builder forgets every ad-hoc metric of its type).
     *
     * @throws UnknownMetricException for a key that is not registered
     */
    public function forget(string|Metric $metric): void
    {
        ResultCache::forget($this->cacheScope($metric));
    }

    /**
     * Forget every cached metric result, on any cache store.
     */
    public function flushCache(): void
    {
        ResultCache::flush();
    }

    /**
     * @throws UnknownMetricException
     */
    protected function cacheScope(string|Metric $metric): string
    {
        if ($metric instanceof Metric) {
            return ResultCache::scopeFor($metric);
        }

        if (! $this->has($metric)) {
            throw UnknownMetricException::forKey($metric);
        }

        return $metric;
    }

    /**
     * @param  class-string<Metric>|Metric|Closure(): Metric  $metric
     */
    private function resolve(string|Metric|Closure $metric): Metric
    {
        if ($metric instanceof Metric) {
            return $metric;
        }

        if ($metric instanceof Closure) {
            return $metric();
        }

        return $this->container->make($metric);
    }
}
