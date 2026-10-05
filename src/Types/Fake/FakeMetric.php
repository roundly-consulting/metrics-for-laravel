<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Types\Fake;

use BadMethodCallException;
use ReflectionMethod;
use ReflectionProperty;
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

    /**
     * The class of the registered metric this fake stands in for, when there is one.
     *
     * @var class-string<Metric>|null
     */
    private ?string $standsInFor = null;

    public function __construct(?Result $result = null)
    {
        $this->result = $result ?? new FakeResult([]);

        parent::__construct();

        // A canned result is never cached: a persistent store would hand one test's
        // canned value to the next test that fakes the same key differently.
        $this->dontCache();
    }

    /**
     * Build a fake metric from a canned value: a Metric is used as a copy with caching
     * off, a Result as-is, a number becomes a ValueResult, and an array becomes the raw
     * result envelope. Nothing canned is ever cached.
     */
    public static function fromCanned(mixed $value): Metric
    {
        if ($value instanceof Metric) {
            return (clone $value)->dontCache();
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

    /**
     * Take on the presentation of the registered metric this fake replaces — its name,
     * description, prefix, suffix and formatter — so a dashboard shows it as it would show
     * the real one, and accept that metric's fluent calls.
     */
    public function standingInFor(Metric $metric): self
    {
        $this->standsInFor = $metric::class;

        $this->name = filled($metric->name) ? $metric->name : $metric->humanize(class_basename($metric));
        $this->description = $metric->description;
        $this->prefix = $metric->prefix;
        $this->suffix = $metric->suffix;

        // The formatter is private to Metric.
        $formatter = new ReflectionProperty(Metric::class, 'formatter');
        $formatter->setValue($this, $formatter->getValue($metric));

        return $this;
    }

    /**
     * A public method of the registered metric — `withChangeAgainstPreviousPeriod()`,
     * `groupBy()`, `limit()`, … — is a no-op on its canned stand-in, so code under test
     * configures it as it would the real metric. Anything else throws, as it would there.
     *
     * @param  array<array-key, mixed>  $arguments
     *
     * @throws BadMethodCallException
     */
    public function __call(string $method, array $arguments): static
    {
        if ($this->standsInFor !== null
            && method_exists($this->standsInFor, $method)
            && (new ReflectionMethod($this->standsInFor, $method))->isPublic()) {
            return $this;
        }

        throw new BadMethodCallException(sprintf('Call to undefined method %s::%s()', $this->standsInFor ?? self::class, $method));
    }

    protected function calculate(): Result
    {
        return $this->result;
    }
}
