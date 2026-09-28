<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Types\Fake;

use RoundlyConsulting\Metrics\Metric;
use RoundlyConsulting\Metrics\Testing\MetricsFake;
use RoundlyConsulting\Metrics\Types\Result;
use RoundlyConsulting\Metrics\Types\Value\ValueResult;

/**
 * A metric that returns a canned result, used by {@see MetricsFake}
 * to stand in for a real metric in host-application tests.
 *
 * @internal
 */
final class FakeMetric extends Metric
{
    private readonly Result $result;

    public function __construct(?Result $result = null)
    {
        $this->result = $result ?? new FakeResult([]);

        parent::__construct();

        // A canned result is never cached: a persistent store would hand one test's
        // canned value to the next test that fakes the same key differently.
        $this->dontCache();
    }

    /**
     * Build a fake metric from a canned value: a Metric or Result is used as-is,
     * a number becomes a ValueResult, and an array becomes the raw result envelope.
     */
    public static function fromCanned(mixed $value): Metric
    {
        if ($value instanceof Metric) {
            return $value;
        }

        if ($value instanceof Result) {
            return new self($value);
        }

        if (is_int($value) || is_float($value)) {
            return new self(new ValueResult((float) $value));
        }

        if (is_array($value)) {
            /** @var array<string, mixed> $value */
            return new self(new FakeResult($value));
        }

        return new self(new FakeResult(['value' => $value]));
    }

    protected function calculate(): Result
    {
        return $this->result;
    }
}
