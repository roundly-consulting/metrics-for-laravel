# Changelog

All notable changes to `metrics-for-laravel` are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## Unreleased

Initial public release.

### Added

- Four metric types built from any Eloquent query: value, trend, progress and partition.
- Inline builders on the `Metric` facade (`Metric::value()`, `trend()`, `progress()`,
  `partition()`) or reusable metric classes.
- Count, sum, average, min and max aggregates over date ranges such as today, month to date,
  last quarter or a custom range, resolved in a configurable timezone.
- Period-over-period change, with `compareTo()` for any comparison range.
- Trends by minute, hour, day, week, month or year, with gap filling and multi-series support on
  MySQL, MariaDB, PostgreSQL and SQLite.
- Top-N partitions with an "Other" bucket, and display labels via `labelUsing()`.
- A metric registry and batch dashboards (`Metric::dashboard()`); metrics and dashboards can be
  returned straight from a controller as JSON.
- Result caching, number formatting and typed result accessors.
- `Period` and `Unit` enums with select options and validation rules.
- `php artisan metrics:list` and `metrics:show`, and a `MetricCalculated` event for
  instrumentation.
- `Metric::fake()` for stubbing and asserting metrics in your tests.
