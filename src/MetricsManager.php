<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Facade;
use RoundlyConsulting\Metrics\Exceptions\UnknownMetricException;
use RoundlyConsulting\Metrics\Testing\MetricsFake;
use RoundlyConsulting\Metrics\Types\Partition\PendingPartition;
use RoundlyConsulting\Metrics\Types\Progress\PendingProgress;
use RoundlyConsulting\Metrics\Types\Trend\PendingTrend;
use RoundlyConsulting\Metrics\Types\Value\PendingValue;

class MetricsManager
{
    /**
     * @var array<string, class-string<Metrics>|Metrics|Closure(): Metrics>
     */
    private array $metrics = [];

    public function __construct(private readonly Container $container) {}

    /**
     * Swap the bound manager for a recording {@see MetricsFake} and return it,
     * so host-application tests can stub metric results and assert resolution.
     *
     * @param  array<string, mixed>  $results
     */
    public static function fake(array $results = []): MetricsFake
    {
        $fake = new MetricsFake(app(), $results);

        app()->instance(MetricsManager::class, $fake);
        app()->instance('metrics', $fake);
        Facade::clearResolvedInstance(MetricsManager::class);

        return $fake;
    }

    /**
     * @param  class-string<Metrics>|Metrics|Closure(): Metrics  $metric
     */
    public function register(string $key, string|Metrics|Closure $metric): self
    {
        $this->metrics[$key] = $metric;

        return $this;
    }

    /**
     * @throws UnknownMetricException
     */
    public function get(string $key): Metrics
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
     * @return array<string, Metrics>
     */
    public function all(): array
    {
        $resolved = [];

        foreach ($this->metrics as $key => $metric) {
            $resolved[$key] = $this->resolve($metric)->withKey($key);
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
     * @param  class-string<Metrics>|Metrics|Closure(): Metrics  $metric
     */
    private function resolve(string|Metrics|Closure $metric): Metrics
    {
        if ($metric instanceof Metrics) {
            return $metric;
        }

        if ($metric instanceof Closure) {
            return $metric();
        }

        return $this->container->make($metric);
    }
}
