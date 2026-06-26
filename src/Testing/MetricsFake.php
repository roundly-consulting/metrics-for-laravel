<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Testing;

use Illuminate\Contracts\Container\Container;
use PHPUnit\Framework\Assert;
use RoundlyConsulting\Metrics\Metrics;
use RoundlyConsulting\Metrics\MetricsManager;
use RoundlyConsulting\Metrics\Types\Fake\FakeMetric;

/**
 * A recording variant of {@see MetricsManager} for host-app tests. Registered
 * keys can be swapped for canned results, and every resolution is recorded so
 * assertions can verify it — matching Laravel's `*::fake()` ergonomics
 * (see {@see MetricsManager::fake()}).
 *
 * It lives in src/ so host apps can use it; it depends only on PHPUnit's
 * Assert, which is always present in a Laravel app's dev dependencies.
 */
final class MetricsFake extends MetricsManager
{
    /** @var array<string, int> */
    private array $resolved = [];

    /** @var array<string, Metrics> */
    private array $canned = [];

    /**
     * @param  array<string, mixed>  $results
     */
    public function __construct(Container $container, array $results = [])
    {
        parent::__construct($container);

        foreach ($results as $key => $value) {
            $this->canned[$key] = FakeMetric::fromCanned($value);
        }
    }

    public function get(string $key): Metrics
    {
        $this->resolved[$key] = ($this->resolved[$key] ?? 0) + 1;

        if (array_key_exists($key, $this->canned)) {
            return $this->canned[$key]->withKey($key);
        }

        return parent::get($key);
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->canned) || parent::has($key);
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
}
