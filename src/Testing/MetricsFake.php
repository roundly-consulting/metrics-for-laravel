<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Testing;

use Illuminate\Contracts\Container\Container;
use PHPUnit\Framework\Assert;
use RoundlyConsulting\Metrics\Exceptions\UnknownMetricException;
use RoundlyConsulting\Metrics\Facades\Metrics;
use RoundlyConsulting\Metrics\Metric;
use RoundlyConsulting\Metrics\MetricsManager;
use RoundlyConsulting\Metrics\Types\Fake\FakeMetric;
use Throwable;

/**
 * A recording {@see MetricsManager} for host-app tests, installed by
 * {@see Metrics::fake()}. It keeps every metric the real manager had registered, answers
 * the canned keys with canned results, records every resolution, and records cache
 * invalidation (`forget()`, `flushCache()`) without touching the cache.
 *
 * It lives in src/ so host apps can use it; it depends only on PHPUnit's
 * Assert, which is always present in a Laravel app's dev dependencies.
 */
final class MetricsFake extends MetricsManager
{
    /** @var array<string, int> */
    private array $resolved = [];

    /** @var array<string, Metric> */
    private array $canned = [];

    /** @var list<string> */
    private array $forgotten = [];

    private int $flushes = 0;

    /**
     * @param  array<string, mixed>  $results  canned results keyed by metric key
     * @param  MetricsManager|null  $registry  the manager whose registrations the fake keeps
     */
    public function __construct(Container $container, array $results = [], ?MetricsManager $registry = null)
    {
        parent::__construct($container);

        if ($registry !== null) {
            $this->metrics = $registry->metrics;
        }

        foreach ($results as $key => $value) {
            $this->canned[$key] = FakeMetric::fromCanned($value);
        }
    }

    public function get(string $key): Metric
    {
        $this->resolved[$key] = ($this->resolved[$key] ?? 0) + 1;

        if (array_key_exists($key, $this->canned)) {
            $canned = (clone $this->canned[$key])->withKey($key);

            return $canned instanceof FakeMetric ? $this->standIn($key, $canned) : $canned;
        }

        return parent::get($key);
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->canned) || parent::has($key);
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_values(array_unique([...parent::keys(), ...array_keys($this->canned)]));
    }

    /**
     * @return array<string, Metric>
     */
    public function all(): array
    {
        $resolved = [];

        foreach ($this->keys() as $key) {
            $resolved[$key] = $this->get($key);
        }

        return $resolved;
    }

    public function unregister(string $key): self
    {
        unset($this->canned[$key]);

        parent::unregister($key);

        return $this;
    }

    public function forget(string|Metric $metric): void
    {
        $this->forgotten[] = is_string($metric) ? $this->cannedOrRegistered($metric) : ($metric->key() ?? $metric::class);
    }

    public function flushCache(): void
    {
        $this->flushes++;
    }

    public function assertResolved(string $key, ?int $times = null): void
    {
        $count = $this->resolved[$key] ?? 0;

        Assert::assertGreaterThan(0, $count, "Expected metric [{$key}] to be resolved, but it was not.");

        if ($times !== null) {
            Assert::assertSame(
                $times,
                $count,
                "Expected metric [{$key}] to be resolved {$times} time(s), but it was resolved {$count} time(s).",
            );
        }
    }

    public function assertResolvedTimes(string $key, int $times): void
    {
        $this->assertResolved($key, $times);
    }

    public function assertNotResolved(string $key): void
    {
        Assert::assertSame(
            0,
            $this->resolved[$key] ?? 0,
            "Expected metric [{$key}] not to be resolved, but it was.",
        );
    }

    public function assertNothingResolved(): void
    {
        Assert::assertSame([], $this->resolved, 'Expected no metrics to be resolved, but some were.');
    }

    /**
     * Assert a metric's cached results were forgotten — by registry key, or by class for
     * an unregistered metric.
     */
    public function assertForgotten(string $key): void
    {
        Assert::assertContains($key, $this->forgotten, "Expected the cached results of metric [{$key}] to be forgotten, but they were not.");
    }

    public function assertNotForgotten(string $key): void
    {
        Assert::assertNotContains($key, $this->forgotten, "Expected the cached results of metric [{$key}] not to be forgotten, but they were.");
    }

    public function assertNothingForgotten(): void
    {
        Assert::assertSame([], $this->forgotten, 'Expected no cached metric results to be forgotten, but some were.');
    }

    public function assertCacheFlushed(): void
    {
        Assert::assertGreaterThan(0, $this->flushes, 'Expected the metric result cache to be flushed, but it was not.');
    }

    public function assertCacheNotFlushed(): void
    {
        Assert::assertSame(0, $this->flushes, 'Expected the metric result cache not to be flushed, but it was.');
    }

    /**
     * A canned stand-in dressed as the metric registered under its key. A registered metric
     * the test cannot build (an unbound dependency) leaves it bare: the canned value is
     * what the test asked for.
     */
    private function standIn(string $key, FakeMetric $fake): FakeMetric
    {
        if (! parent::has($key)) {
            return $fake;
        }

        try {
            $registered = parent::get($key);
        } catch (Throwable) {
            return $fake;
        }

        return $fake->standingInFor($registered);
    }

    /**
     * @throws UnknownMetricException
     */
    private function cannedOrRegistered(string $key): string
    {
        if (! $this->has($key)) {
            throw UnknownMetricException::forKey($key);
        }

        return $key;
    }
}
