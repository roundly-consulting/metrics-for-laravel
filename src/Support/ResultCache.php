<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Support;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RoundlyConsulting\Metrics\Metric;
use RoundlyConsulting\Metrics\Types\Result;

/**
 * The result cache's storage format and invalidation.
 *
 * Invalidation works on every cache store, tags or not: each cached entry carries the
 * **generation** it was written under — one token for the whole cache plus one per scope
 * (the registry key, or the metric class for ad-hoc metrics). Forgetting a scope or
 * flushing the cache writes a fresh token, so every entry written before it no longer
 * matches and is recalculated on the next read. Stale entries are overwritten in place,
 * so nothing is orphaned under an old key.
 *
 * Entries store the result's class and its array form rather than the object, because
 * Laravel 13's `cache.serializable_classes` refuses to unserialize arbitrary objects.
 *
 * @internal
 */
final class ResultCache
{
    public static function repository(): Repository
    {
        $store = config('metrics.cache.store');

        return Cache::store(is_string($store) ? $store : null);
    }

    public static function prefix(): string
    {
        $prefix = config('metrics.cache.prefix', 'metrics');

        return is_string($prefix) ? $prefix : 'metrics';
    }

    /**
     * The scope a metric's cached results are forgotten under: its registry key, or its
     * class when it was built ad hoc.
     */
    public static function scopeFor(Metric $metric): string
    {
        return $metric->key() ?? $metric::class;
    }

    /**
     * The current generation token for a scope: changes whenever the scope is forgotten
     * or the whole cache is flushed.
     */
    public static function generation(string $scope): string
    {
        $global = self::globalKey();
        $scoped = self::scopeKey($scope);

        $tokens = self::repository()->many([$global, $scoped]);

        return self::token($tokens[$global] ?? null).'.'.self::token($tokens[$scoped] ?? null);
    }

    /**
     * Invalidate every cached result of one scope.
     */
    public static function forget(string $scope): void
    {
        self::repository()->forever(self::scopeKey($scope), Str::random(16));
    }

    /**
     * Invalidate every cached metric result.
     */
    public static function flush(): void
    {
        self::repository()->forever(self::globalKey(), Str::random(16));
    }

    /**
     * @return array{type: class-string<Result>, data: array<string, mixed>, generation: string}
     */
    public static function envelope(Result $result, string $generation): array
    {
        return [
            'type' => $result::class,
            'data' => $result->toArray(),
            'generation' => $generation,
        ];
    }

    /**
     * Rebuild a cached result, or null when the entry is missing, malformed or was
     * written under an older generation.
     */
    public static function restore(mixed $payload, string $generation): ?Result
    {
        if (! is_array($payload) || ($payload['generation'] ?? null) !== $generation) {
            return null;
        }

        $type = $payload['type'] ?? null;
        $data = $payload['data'] ?? null;

        if (! is_string($type) || ! is_array($data) || ! is_subclass_of($type, Result::class)) {
            return null;
        }

        /** @var array<string, mixed> $data */
        return $type::fromArray($data);
    }

    private static function globalKey(): string
    {
        return self::prefix().':generation';
    }

    private static function scopeKey(string $scope): string
    {
        return self::prefix().':generation:'.md5($scope);
    }

    private static function token(mixed $value): string
    {
        return is_string($value) ? $value : '0';
    }
}
