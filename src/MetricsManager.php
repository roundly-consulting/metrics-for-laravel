<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics;

use Closure;
use Illuminate\Contracts\Container\Container;
use RoundlyConsulting\Metrics\Exceptions\UnknownMetricException;
use RoundlyConsulting\Metrics\Types\Partition\PendingPartition;
use RoundlyConsulting\Metrics\Types\Progress\PendingProgress;
use RoundlyConsulting\Metrics\Types\Trend\PendingTrend;
use RoundlyConsulting\Metrics\Types\Value\PendingValue;

final class MetricsManager
{
    /**
     * @var array<string, class-string<Metrics>|Metrics|Closure(): Metrics>
     */
    private array $metrics = [];

    public function __construct(private readonly Container $container) {}

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

        return $this->resolve($this->metrics[$key]);
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->metrics);
    }

    /**
     * @return array<string, Metrics>
     */
    public function all(): array
    {
        return array_map(
            fn (string|Metrics|Closure $metric): Metrics => $this->resolve($metric),
            $this->metrics,
        );
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
