<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RoundlyConsulting\Metrics\Concerns\Cacheable;
use RoundlyConsulting\Metrics\Concerns\FormatsValues;
use RoundlyConsulting\Metrics\Enums\Period;
use RoundlyConsulting\Metrics\Events\MetricCalculated;
use RoundlyConsulting\Metrics\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\Metrics\Exceptions\UnexpectedResultException;
use RoundlyConsulting\Metrics\Support\JsonEnvelope;
use RoundlyConsulting\Metrics\Support\MetricsConfig;
use RoundlyConsulting\Metrics\Support\ResultCache;
use RoundlyConsulting\Metrics\Traits\Description;
use RoundlyConsulting\Metrics\Traits\Humanize;
use RoundlyConsulting\Metrics\Traits\Makeable;
use RoundlyConsulting\Metrics\Traits\Name;
use RoundlyConsulting\Metrics\Traits\Prefix;
use RoundlyConsulting\Metrics\Traits\Ranges;
use RoundlyConsulting\Metrics\Traits\Rounding;
use RoundlyConsulting\Metrics\Traits\Suffix;
use RoundlyConsulting\Metrics\Types\Result;
use RoundlyConsulting\PackageToolkit\Support\Config;

/**
 * The base of every metric. Extend a typed base — `Value`, `Trend`, `Progress` or
 * `Partition` — and implement `calculate()`; read the typed result with `result()` or the
 * JSON envelope with `toArray()`.
 *
 * @phpstan-consistent-constructor
 *
 * @implements Arrayable<string, mixed>
 */
abstract class Metric implements Arrayable, Responsable
{
    use Cacheable;
    use Description;
    use FormatsValues;
    use Humanize;
    use Makeable;
    use Name;
    use Prefix;
    use Ranges;
    use Rounding;
    use Suffix;

    /**
     * The registry key this metric was resolved under, when known.
     */
    protected ?string $resolvedKey = null;

    public function __construct()
    {
        $this->applyConfiguredDefaults();

        $this->setup();
    }

    /**
     * The registry key this metric was resolved under, or null for ad-hoc metrics.
     */
    public function key(): ?string
    {
        return $this->resolvedKey;
    }

    /**
     * Tag this metric with the registry key it was resolved under.
     *
     * @internal
     */
    public function withKey(?string $key): static
    {
        $this->resolvedKey = $key;

        return $this;
    }

    protected function applyConfiguredDefaults(): void
    {
        // Not set (absent, null or blank) keeps the metric's own default; anything else must
        // be valid — an unknown range or a non-integer precision throws instead of being
        // silently ignored.
        if (! MetricsConfig::isUnset(config('metrics.default_range'))) {
            $this->range = Config::using(InvalidConfigurationException::class)
                ->enum('metrics.default_range', Period::class)->value;
        }

        $this->roundingPrecision = Config::using(InvalidConfigurationException::class)
            ->integer('metrics.precision', $this->roundingPrecision);
    }

    protected function setup(): void
    {
        //
    }

    abstract protected function calculate(): Result;

    /**
     * The metric's typed result — calculated, or restored from the result cache. The
     * typed bases narrow it: `Value` returns a `ValueResult`, `Trend` a `TrendResult`,
     * `Progress` a `ProgressResult` and `Partition` a `PartitionResult`.
     */
    public function result(): Result
    {
        return $this->computeResult();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => filled($this->name) ? $this->name : $this->humanize(class_basename($this)),
            'description' => $this->description,
            'prefix' => $this->prefix,
            'suffix' => $this->suffix,
            'range' => [
                'current' => $this->range,
                'available' => $this->ranges(),
                'custom' => [
                    'start' => $this->customRangeStart,
                    'end' => $this->customRangeEnd,
                ],
            ],
            'result' => $this->applyFormatting($this->result()->toArray()),
        ];
    }

    /**
     * Return the metric's envelope as a JSON response so a controller can
     * `return Metrics::get('x')` directly. Keyed maps always encode as JSON objects.
     * Authorization stays the host app's responsibility.
     *
     * @param  Request  $request
     */
    public function toResponse($request): JsonResponse
    {
        return new JsonResponse(JsonEnvelope::of($this->toArray()));
    }

    /**
     * The result, narrowed to the type a typed base promises.
     *
     * @template TResult of Result
     *
     * @param  class-string<TResult>  $type
     * @return TResult
     *
     * @throws UnexpectedResultException when `calculate()` returned another result type
     */
    protected function resultOf(string $type): Result
    {
        $result = $this->computeResult();

        if (! $result instanceof $type) {
            throw UnexpectedResultException::for(static::class, $type, $result::class);
        }

        return $result;
    }

    protected function computeResult(): Result
    {
        $startedAt = hrtime(true);

        $cacheKey = $this->cachingEnabled() ? $this->resolveCacheKey() : null;

        if ($cacheKey === null) {
            $result = $this->calculate();

            $this->dispatchCalculated($startedAt, fromCache: false);

            return $result;
        }

        $repository = $this->cacheRepository();
        $generation = ResultCache::generation(ResultCache::scopeFor($this));

        $cached = ResultCache::restore($repository->get($cacheKey), $generation);

        if ($cached !== null) {
            $this->dispatchCalculated($startedAt, fromCache: true);

            return $cached;
        }

        $result = $this->calculate();

        $repository->put($cacheKey, ResultCache::envelope($result, $generation), $this->resolveCacheTtl());

        $this->dispatchCalculated($startedAt, fromCache: false);

        return $result;
    }

    private function dispatchCalculated(int|float $startedAt, bool $fromCache): void
    {
        event(new MetricCalculated(
            key: $this->resolvedKey,
            range: $this->range,
            durationMs: (hrtime(true) - $startedAt) / 1_000_000,
            fromCache: $fromCache,
        ));
    }
}
