<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Types\Fake;

use RoundlyConsulting\Metrics\Metrics;
use RoundlyConsulting\Metrics\Testing\MetricsFake;
use RoundlyConsulting\Metrics\Types\Result;
use RoundlyConsulting\Metrics\Types\Value\ValueResult;

/**
 * A metric that returns a canned result, used by {@see MetricsFake}
 * to stand in for a real metric in host-application tests.
 */
final class FakeMetric extends Metrics
{
    private readonly Result $result;

    public function __construct(?Result $result = null)
    {
        $this->result = $result ?? new FakeResult([]);

        parent::__construct();
    }

    /**
     * Build a fake metric from a canned value: a Metrics or Result is used as-is,
     * a number becomes a ValueResult, and an array becomes the raw result envelope.
     */
    public static function fromCanned(mixed $value): Metrics
    {
        if ($value instanceof Metrics) {
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
