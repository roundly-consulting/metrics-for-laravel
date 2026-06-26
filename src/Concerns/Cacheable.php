<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Concerns;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

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

        return (bool) config('metrics.cache.enabled', false);
    }

    protected function resolveCacheTtl(): int
    {
        if ($this->cacheTtl !== null) {
            return $this->cacheTtl;
        }

        $ttl = config('metrics.cache.ttl', 300);

        return is_int($ttl) ? $ttl : 300;
    }

    protected function cacheRepository(): Repository
    {
        $store = config('metrics.cache.store');

        return Cache::store(is_string($store) ? $store : null);
    }

    protected function resolveCacheKey(): string
    {
        if ($this->customCacheKey !== null) {
            return $this->customCacheKey;
        }

        $prefix = config('metrics.cache.prefix', 'metrics');

        $parts = array_merge(
            [static::class, $this->range, $this->customRangeStart, $this->customRangeEnd, $this->timezoneOverride],
            $this->cacheDiscriminators(),
        );

        return (is_string($prefix) ? $prefix : 'metrics').':'.md5(serialize($parts));
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
