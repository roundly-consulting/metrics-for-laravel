<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RoundlyConsulting\Metrics\Concerns\Cacheable;
use RoundlyConsulting\Metrics\Concerns\FormatsValues;
use RoundlyConsulting\Metrics\Events\MetricCalculated;
use RoundlyConsulting\Metrics\Traits\Description;
use RoundlyConsulting\Metrics\Traits\Humanize;
use RoundlyConsulting\Metrics\Traits\Makeable;
use RoundlyConsulting\Metrics\Traits\Name;
use RoundlyConsulting\Metrics\Traits\Prefix;
use RoundlyConsulting\Metrics\Traits\Ranges;
use RoundlyConsulting\Metrics\Traits\Rounding;
use RoundlyConsulting\Metrics\Traits\Suffix;
use RoundlyConsulting\Metrics\Types\Result;

/**
 * @phpstan-consistent-constructor
 *
 * @implements Arrayable<string, mixed>
 */
abstract class Metrics implements Arrayable, Responsable
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
        $range = config('metrics.default_range');

        if (is_string($range)) {
            $this->range = $range;
        }

        $precision = config('metrics.precision');

        if (is_int($precision)) {
            $this->roundingPrecision = $precision;
        }
    }

    protected function setup(): void
    {
        //
    }

    abstract protected function calculate(): Result;

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
            'result' => $this->applyFormatting($this->resolveResultArray()),
        ];
    }

    /**
     * Return the metric's envelope as a JSON response so a controller can
     * `return Metric::get('x')` directly. Authorization stays the host app's
     * responsibility.
     *
     * @param  Request  $request
     */
    public function toResponse($request): JsonResponse
    {
        return new JsonResponse($this->toArray());
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolveResultArray(): array
    {
        $startedAt = hrtime(true);

        if (! $this->cachingEnabled()) {
            $result = $this->calculate()->toArray();

            $this->dispatchCalculated($startedAt, fromCache: false);

            return $result;
        }

        $cacheKey = $this->resolveCacheKey();
        $fromCache = $this->cacheRepository()->has($cacheKey);

        $result = $this->cacheRepository()->remember(
            $cacheKey,
            $this->resolveCacheTtl(),
            fn (): array => $this->calculate()->toArray(),
        );

        $this->dispatchCalculated($startedAt, $fromCache);

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
