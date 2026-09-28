# Changelog

All notable changes to `metrics-for-laravel` are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## Unreleased

Initial public release.

### Added

- Four metric types built from any Eloquent query: value, trend, progress and partition.
- Inline builders on the `Metrics` facade (`Metrics::value()`, `trend()`, `progress()`,
  `partition()`) or reusable metric classes extending `Value`, `Trend`, `Progress` or
  `Partition` (all built on the `Metric` base class).
- `result()` on every metric returns its typed result object (`ValueResult`, `TrendResult`,
  `ProgressResult`, `PartitionResult`), cached or fresh; `toArray()` stays the JSON envelope.
- Count, sum, average, min and max aggregates over date ranges such as today, month to date,
  last quarter or a custom range, resolved in a configurable timezone.
- Period-over-period change, with `compareTo()` for any comparison range.
- Trends by minute, hour, day, week, month or year, with gap filling and multi-series support on
  MySQL, MariaDB, PostgreSQL and SQLite.
- Top-N partitions with an "Other" bucket, and display labels via `labelUsing()`.
- A metric registry (`register()`, `unregister()`, `get()`, `has()`, `keys()`, `all()`) and
  batch dashboards (`Metrics::dashboard()`); metrics and dashboards can be returned straight
  from a controller as JSON. The injectable `MetricsManager` serves the same API without the
  facade.
- Result caching with `Metrics::forget('key')` and `Metrics::flushCache()`, which invalidate on
  any cache store (custom cache keys included); number formatting and typed result accessors.
- `Period` and `Unit` enums with select options and validation rules.
- `php artisan metrics:list` and `metrics:show`, and a `MetricCalculated` event for
  instrumentation.
- `Metrics::fake()` for stubbing and asserting metrics in your tests: it keeps your registered
  metrics, records resolutions and cache invalidation (`assertForgotten()`,
  `assertCacheFlushed()` and their negatives), and is what injected managers receive too.

### Changed

- The facade is `Metrics` (was `Metric`) and the abstract base class is `Metric` (was
  `Metrics`); the global alias follows. `fake()` is a real static on the facade.
- `ProgressResult` is a `ValueResult`: it gains `change()` (also under `change.percentage` in the
  envelope), `previousProgress()` and `avoid()`.
- `Result` implementations must provide `fromArray()`; the cache stores results as plain data
  (Laravel 13's `cache.serializable_classes` refuses objects).

### Fixed

- Cached inline builders and registered metrics of the same class no longer share one cache
  entry: the key now includes the registry key and the builder's query and aggregate.
- `Metrics::fake()` no longer drops the metrics registered before it, and never caches a canned
  result.
