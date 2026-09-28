<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Concerns;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Metrics\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\Metrics\Support\ResultCache;
use RoundlyConsulting\Metrics\Support\Timezones;
use RoundlyConsulting\PackageToolkit\Support\Config;

trait Cacheable
{
    private ?bool $shouldCache = null;

    private ?int $cacheTtl = null;

    private ?string $customCacheKey = null;

    /**
     * Enable caching for this metric, optionally overriding the TTL (seconds).
     */
    public function cache(?int $ttl = null): static
    {
        $this->shouldCache = true;

        if ($ttl !== null) {
            $this->cacheTtl = $ttl;
        }

        return $this;
    }

    /**
     * Enable caching until the given moment, or for the given number of seconds.
     */
    public function cacheFor(DateTimeInterface|int $ttl): static
    {
        $this->shouldCache = true;

        $this->cacheTtl = $ttl instanceof DateTimeInterface
            ? max(0, (int) round((float) CarbonImmutable::now()->diffInSeconds($ttl, false)))
            : $ttl;

        return $this;
    }

    /**
     * Override the cache key used to store this metric's result.
     */
    public function cacheKey(string $key): static
    {
        $this->customCacheKey = $key;

        return $this;
    }

    /**
     * Disable caching for this metric regardless of configuration.
     */
    public function dontCache(): static
    {
        $this->shouldCache = false;

        return $this;
    }

    protected function cachingEnabled(): bool
    {
        if ($this->shouldCache !== null) {
            return $this->shouldCache;
        }

        // `.env` hands the flag over as a string ('1', 'off', …), so parse it
        // as a boolean rather than casting: (bool) 'off' would switch caching on.
        return Config::boolean('metrics.cache.enabled');
    }

    /**
     * The per-metric TTL, else `metrics.cache.ttl` in seconds — accepted as an int
     * or as the digit string `.env` produces (`METRICS_CACHE_TTL=600`), between one
     * second and one year. A present but unusable value throws rather than
     * silently falling back to the default.
     */
    protected function resolveCacheTtl(): int
    {
        if ($this->cacheTtl !== null) {
            return $this->cacheTtl;
        }

        return Config::using(InvalidConfigurationException::class)
            ->intBetween('metrics.cache.ttl', 1, 31_536_000, 300);
    }

    protected function cacheRepository(): Repository
    {
        return ResultCache::repository();
    }

    /**
     * The custom key when one is set, else a key derived from everything that makes the
     * result unique: the class, the registry key, the range, the reporting and storage
     * timezones, the precision and rounding mode, the ad-hoc builder's query
     * ({@see cacheIdentity()}) and the type's options.
     */
    protected function resolveCacheKey(): string
    {
        if ($this->customCacheKey !== null) {
            return $this->customCacheKey;
        }

        $parts = array_merge(
            [
                static::class,
                $this->resolvedKey,
                $this->range,
                $this->customRangeStart,
                $this->customRangeEnd,
                'timezone' => $this->reportingTimezone(),
                'storage_timezone' => Timezones::storage(),
                'precision' => $this->roundingPrecision,
                'rounding' => $this->roundingMode->name,
            ],
            ['identity' => $this->cacheIdentity()],
            $this->cacheDiscriminators(),
        );

        return ResultCache::prefix().':'.md5(serialize($parts));
    }

    /**
     * What the metric computes, when its class alone does not say — an ad-hoc builder's
     * query and aggregate. A metric class's `calculate()` is fixed by the class itself.
     *
     * @return array<array-key, mixed>
     */
    protected function cacheIdentity(): array
    {
        return [];
    }

    /**
     * The identity of an Eloquent query: its connection, model, SQL and bindings.
     *
     * @param  Builder<covariant Model>|null  $query
     * @return array<array-key, mixed>
     */
    protected function queryIdentity(?Builder $query): array
    {
        if ($query === null) {
            return [];
        }

        return [
            $query->getModel()->getConnectionName(),
            $query->getModel()::class,
            $query->toSql(),
            $query->getBindings(),
        ];
    }

    /**
     * Extra values that make this metric's result unique (e.g. unit, target).
     *
     * @return array<array-key, mixed>
     */
    protected function cacheDiscriminators(): array
    {
        return [];
    }
}
