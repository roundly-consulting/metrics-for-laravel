# Changelog

All notable changes to `metrics-for-laravel` are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## Unreleased

## 1.1.0 - 2026-10-05

This release fixes eleven bugs. Six of them change output you may rely on. They are marked
**Contract change** below, so check those before upgrading.

### Changed

- Metric classes that override the protected cache hooks must match the new signatures:
  `cacheIdentity(): ?array` (returning `null` skips the cache), `resolveCacheKey(): ?string`
  and `resolveCacheTtl(): DateTimeInterface|int`. The trend helper `totalsAcrossSeries()` was
  removed. `rollUp()` and `weightedAverage()` are still there.
- Maintenance: `composer.json` `homepage` and `support.docs` now link to the documentation page.
- Documentation: the README hero image uses an absolute URL, so it renders on Packagist.

### Fixed

- The result cache key of a class-based metric now includes the state its class declares —
  constructor arguments and other properties (a model as its class and key, a query as its
  SQL and bindings, an enum as its value, a service as its class). Two instances built for two
  tenants or two columns no longer share one cached result. A metric holding a closure is
  calculated without the cache. Override `cacheIdentity()` for state read from `auth()` or
  the request.
- Trends and partitions drop the caller's `ORDER BY` from their grouped SQL: a partition's
  top N is ranked by its aggregate again, trend buckets come back in date order, and an
  ordered query (`latest()`) no longer fails the `GROUP BY` on PostgreSQL and MySQL.
- A trend's date column is quoted in the bucket SQL, so a camelCase or reserved-word column
  (`occurredAt`, `order`) works on every range and timezone. A custom `QueryExpression` now
  receives the quoted column.
- **Contract change:** a grouped trend's `trends` is the ungrouped trend over the same range —
  the metric's own aggregate over every row, rounded once — instead of the sum of the series.
  Averages, minimums and maximums change, sums and counts change where rounding differed,
  and a grouped trend over a range with no rows now returns the zero-filled buckets.
- **Contract change:** a NULL group and an empty-string group (and PostgreSQL's `false`) no
  longer overwrite each other in partitions and trend series: colliding groups are merged with
  the metric's aggregate, so no row is dropped. A boolean group is keyed `0` / `1` on every
  database (PostgreSQL's `false` used to be `''`).
- **Contract change:** a custom range whose end is a bare date (`2026-01-31`) now ends at the
  end of that day, and its previous period lines up on whole days. An end with a time is
  unchanged.
- `Metrics::fake()` stands a canned value in with the registered metric's name, description,
  prefix, suffix and formatter, and accepts that metric's fluent calls
  (`withChangeAgainstPreviousPeriod()`, …) as no-ops. A method the metric doesn't have throws
  `BadMethodCallException`.
- **Contract change:** the percentage change is measured against the size of the previous
  value, so from -100 to -50 is +50% (it was -50%). From zero, a fall is -100% (it was 0%).
- **Contract change (JSON):** without a comparison, `change.is_increase` — and on a progress
  metric `change.progress` and `change.value` — are `null` instead of a comparison with
  nothing. `isIncrease()` returns `false` without a comparison.
- `cacheFor(DateTimeInterface)` keeps the moment and resolves it when the entry is written, so
  a registered template stops caching at that moment instead of a fixed number of seconds
  after its first read.
- **Contract change (JSON):** a metric's or dashboard's JSON response always encodes `trends`,
  `series`, `partitions`, `labels` and `formatted` as objects. A map keyed `0`, `1`, … used to
  encode as an array. `toArray()` is unchanged.

## 1.0.0 - 2026-10-03

Initial public release.

### Added

- Four metric types built from any Eloquent query: value, trend, progress and partition.
- Inline builders on the `Metrics` facade (`Metrics::value()`, `trend()`, `progress()`,
  `partition()`) or reusable metric classes extending `Value`, `Trend`, `Progress` or
  `Partition` (all built on the `Metric` base class).
- `result()` on every metric returns its typed result object (`ValueResult`, `TrendResult`,
  `ProgressResult`, `PartitionResult`), cached or fresh; `toArray()` stays the JSON envelope.
- Count, sum, average, min and max aggregates over date ranges such as today, month to date,
  last quarter or a custom range, resolved in a configurable reporting timezone: range bounds
  are converted to the app timezone the rows are stored in, and trend buckets are labelled in
  the reporting timezone (daylight saving included). Weeks are ISO weeks in every locale.
- Period-over-period change, with `compareTo()` for any comparison range.
- Trends by minute, hour, day, week, month or year, with gap filling and multi-series support on
  MySQL, MariaDB, PostgreSQL and SQLite; other drivers plug in through
  `config('metrics.trend_drivers')`, the one driver registry.
- Top-N partitions with an "Other" bucket rolled up with the metric's own aggregate, and
  display labels via `labelUsing()` (resolved on every read, never cached).
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
  result — a canned `Metric` instance included.
- Ranges resolved in `metrics.timezone` are converted to the storage timezone before querying,
  instead of being applied to UTC rows as local wall clock; trend buckets are labelled in the
  reporting timezone.
- Gap filling starts at the bucket holding the range start, so the current week/month/year is
  never dropped and monthly steps no longer overflow past short months; buckets the database
  returns are never discarded. Bucket keys parse with `!` formats (no "today" fill-ins).
- Week ranges start on Monday whatever the locale; quarter and year-to-date ranges no longer
  overflow on month-end days; a custom range's previous period is exactly as long and ends one
  second before it.
- A partition's "Other" bucket no longer overwrites a real group of that name, and `count()`
  without a value column counts the `NULL` group's rows.
- The result cache key includes precision, rounding mode, the reporting and app timezones and
  the translated "Other" label.
- `Metrics::get()` hands out a copy of a registered instance, so one caller's `range()` or
  `timezone()` never reaches the next; trend and partition builders no longer add their SQL to
  the query they were given.
