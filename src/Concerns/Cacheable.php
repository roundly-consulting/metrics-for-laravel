<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Concerns;

use BackedEnum;
use Closure;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use ReflectionClass;
use RoundlyConsulting\Metrics\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\Metrics\Support\ResultCache;
use RoundlyConsulting\Metrics\Support\Timezones;
use RoundlyConsulting\PackageToolkit\Support\Config;
use UnitEnum;

trait Cacheable
{
    private ?bool $shouldCache = null;

    /**
     * Seconds, or the moment the entry expires — kept as a moment and only turned into a
     * lifetime when the entry is written, since a registered instance is a template that
     * may be resolved long after `cacheFor()` was called.
     */
    private DateTimeInterface|int|null $cacheTtl = null;

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

        $this->cacheTtl = $ttl instanceof DateTimeInterface ? DateTimeImmutable::createFromInterface($ttl) : $ttl;

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
        // A typo throws the package's own exception, like an unusable ttl.
        return Config::using(InvalidConfigurationException::class)
            ->boolean('metrics.cache.enabled');
    }

    /**
     * The per-metric TTL — seconds, or the moment set by `cacheFor()`, which the cache
     * resolves as it writes the entry (a moment already past stores nothing) — else
     * `metrics.cache.ttl` in seconds, accepted as an int or as the digit string `.env`
     * produces (`METRICS_CACHE_TTL=600`), between one second and one year. A present but
     * unusable value throws rather than silently falling back to the default.
     */
    protected function resolveCacheTtl(): DateTimeInterface|int
    {
        if ($this->cacheTtl !== null) {
            return $this->cacheTtl;
        }

        return Config::using(InvalidConfigurationException::class)
            ->integer('metrics.cache.ttl', 300, min: 1, max: 31_536_000);
    }

    protected function cacheRepository(): Repository
    {
        return ResultCache::repository();
    }

    /**
     * The custom key when one is set, else a key derived from everything that makes the
     * result unique: the class, the registry key, the range, the reporting and storage
     * timezones, the precision and rounding mode, what the metric computes
     * ({@see cacheIdentity()}) and the type's options. Null when that has no stable
     * identity: the metric is then calculated without the cache.
     */
    protected function resolveCacheKey(): ?string
    {
        if ($this->customCacheKey !== null) {
            return $this->customCacheKey;
        }

        $identity = $this->cacheIdentity();

        if ($identity === null) {
            return null;
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
            ['identity' => $identity],
            $this->cacheDiscriminators(),
        );

        return ResultCache::prefix().':'.md5(serialize($parts));
    }

    /**
     * What the metric computes, beyond its class: an ad-hoc builder's query and aggregate,
     * or the state a metric class declares itself — its constructor arguments and other
     * properties ({@see declaredState()}). Two instances of one class built for two tenants
     * or two columns therefore never share an entry.
     *
     * Override it when the result depends on state the metric reads while calculating —
     * `auth()`, the request — rather than holding it in a property.
     *
     * @return array<array-key, mixed>|null null when the state has no stable identity
     */
    protected function cacheIdentity(): ?array
    {
        return $this->declaredState();
    }

    /**
     * Every property declared by the classes between this metric and the package's base
     * class it extends. A model counts as its class and key, a query as its SQL and
     * bindings, an enum as its value and a moment as its instant; any other object (an
     * injected service) as its class. Null when a value has no stable identity — a closure
     * or a resource.
     *
     * @return array<string, mixed>|null
     */
    private function declaredState(): ?array
    {
        $state = [];
        $stable = true;

        for ($class = new ReflectionClass($this); $class !== false && ! self::isPackageClass($class); $class = $class->getParentClass()) {
            foreach ($class->getProperties() as $property) {
                if ($property->isStatic() || $property->getDeclaringClass()->getName() !== $class->getName()) {
                    continue;
                }

                $state[$class->getName().'::'.$property->getName()] = $property->isInitialized($this)
                    ? $this->stateIdentity($property->getValue($this), $stable)
                    : null;
            }
        }

        return $stable ? $state : null;
    }

    private function stateIdentity(mixed $value, bool &$stable): mixed
    {
        if ($value === null || is_scalar($value)) {
            return $value;
        }

        if (is_array($value)) {
            $identity = [];

            foreach ($value as $key => $item) {
                $identity[$key] = $this->stateIdentity($item, $stable);
            }

            return $identity;
        }

        if (! is_object($value) || $value instanceof Closure) {
            $stable = false;

            return null;
        }

        return match (true) {
            $value instanceof UnitEnum => [$value::class, $value instanceof BackedEnum ? $value->value : $value->name],
            $value instanceof DateTimeInterface => $value->format('Y-m-d H:i:s.u e'),
            $value instanceof Model => [$value::class, $value->getConnectionName(), $value->getKey() ?? $this->stateIdentity($value->getAttributes(), $stable)],
            $value instanceof Builder => $this->queryIdentity($value),
            $value instanceof QueryBuilder => [$value->getConnection()->getDatabaseName(), $value->toSql(), $value->getBindings()],
            default => $value::class,
        };
    }

    /**
     * Whether a class is one of the package's own — where the state walk stops.
     *
     * @param  ReflectionClass<object>  $class
     */
    private static function isPackageClass(ReflectionClass $class): bool
    {
        return str_starts_with((string) $class->getFileName(), dirname(__DIR__).DIRECTORY_SEPARATOR);
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
