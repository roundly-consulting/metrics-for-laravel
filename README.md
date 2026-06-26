<!-- roundly-hero:start -->
<p align="center">
  <a href="https://roundly-consulting.com/open-source/docs/metrics-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=metrics-for-laravel">
    <img src="art/hero.png" alt="Metrics for Laravel — Roundly open source" width="100%">
  </a>
</p>
<!-- roundly-hero:end -->

# Metrics for Laravel

Calculate value, trend, progress, and partition metrics from any Eloquent query.

`metrics-for-laravel` turns an Eloquent query into a ready-to-render metric — a single
value with period-over-period change, a time series for charts, a progress bar against a
target, or a partitioned breakdown for pie/bar charts. Build a metric inline in one fluent
expression, or define a small reusable class. The package handles date ranges, aggregation,
grouping, caching, and formatting, and hands back clean JSON-friendly data — Nova-style
metrics without Nova, for any front-end.

## Requirements

- PHP 8.4+
- Laravel 12 or 13

## Installation

```bash
composer require roundly-consulting/metrics-for-laravel
```

The service provider and the `Metric` facade are auto-discovered. The package works against
your existing models and tables — there are no migrations.

Optionally publish the config file:

```bash
php artisan vendor:publish --tag="metrics-config"
```

## Quick start

Build a metric inline with the `Metric` facade — no class required:

```php
use App\Models\User;
use RoundlyConsulting\Metrics\Enums\Period;
use RoundlyConsulting\Metrics\Facades\Metric;

$data = Metric::value()
    ->count(User::query())
    ->range(Period::Today)
    ->withChangeAgainstPreviousPeriod()
    ->toArray();
```

The facade exposes one builder per metric type — `Metric::value()`, `Metric::trend()`,
`Metric::progress()`, and `Metric::partition()`:

```php
Metric::trend()->count(User::query(), 'created_at')->daily()->range(Period::MonthToDate)->toArray();
Metric::progress()->sum(Order::query(), 'total')->target(10000)->range(Period::MonthToDate)->toArray();
Metric::partition()->count(User::query(), 'plan')->toArray();
```

## Metric types (reusable classes)

For metrics you reuse across a dashboard, define a class by extending the base type and
implementing `calculate()`, which returns the matching result object.

| Type | Extend | `calculate()` returns | Good for |
|---|---|---|---|
| Value | `RoundlyConsulting\Metrics\Types\Value\Value` | `ValueResult` | a single number, optionally vs. the previous period |
| Trend | `RoundlyConsulting\Metrics\Types\Trend\Trend` | `TrendResult` | a time series (line/area chart) |
| Progress | `RoundlyConsulting\Metrics\Types\Progress\Progress` | `ProgressResult` | current value vs. a target (progress bar) |
| Partition | `RoundlyConsulting\Metrics\Types\Partition\Partition` | `PartitionResult` | a grouped breakdown (pie/bar chart) |

### Value

```php
use App\Models\User;
use RoundlyConsulting\Metrics\Types\Result;
use RoundlyConsulting\Metrics\Types\Value\Value;

final class RegisteredUsers extends Value
{
    protected function calculate(): Result
    {
        return $this->count(User::query());
    }
}

RegisteredUsers::make()->range('TODAY')->toArray();
```

### Trend

```php
use App\Models\User;
use RoundlyConsulting\Metrics\Types\Result;
use RoundlyConsulting\Metrics\Types\Trend\Trend;

final class RegisteredUsersTrend extends Trend
{
    protected function calculate(): Result
    {
        return $this->count(User::query(), 'created_at');
    }
}

RegisteredUsersTrend::make()->hourly()->range('TODAY')->toArray();
```

### Progress

A Progress metric compares the current value to a `target`. Use `shouldBeAvoided()` when the
target is a ceiling you want to stay **under** (an error budget or a spend cap) rather than a
goal to **reach**.

```php
use App\Models\Subscription;
use RoundlyConsulting\Metrics\Types\Progress\Progress;
use RoundlyConsulting\Metrics\Types\Result;

final class MonthlyRevenueGoal extends Progress
{
    protected function setup(): void
    {
        $this->target(10000)->range('MTD');
    }

    protected function calculate(): Result
    {
        return $this->sum(Subscription::query(), 'amount');
    }
}
```

### Partition

```php
use App\Models\User;
use RoundlyConsulting\Metrics\Types\Partition\Partition;
use RoundlyConsulting\Metrics\Types\Result;

final class UsersByPlan extends Partition
{
    protected function calculate(): Result
    {
        // count of users grouped by the `plan` column
        return $this->count(User::query(), 'plan');
    }
}
```

## Registry

Register your dashboard's metrics under short keys and resolve them by name — ideal for an
endpoint that returns many tiles at once:

```php
use RoundlyConsulting\Metrics\Facades\Metric;

// in a service provider boot()
Metric::register('registered_users', RegisteredUsers::class);
Metric::register('revenue', MonthlyRevenueGoal::class);
Metric::register('users_by_plan', fn () => UsersByPlan::make());

// in a controller
return response()->json(
    collect(Metric::all())->map->toArray()
);

// or a single metric by key, applying the request's period
return response()->json(
    Metric::get($key)->range($request->input('period', 'TODAY'))->toArray()
);
```

Register a class-string, an instance, or a closure. `Metric::get()` throws
`RoundlyConsulting\Metrics\Exceptions\UnknownMetricException` for an unregistered key.

## Configuring a metric

Override presentation and behaviour inline (fluent calls) or inside the metric's `setup()`
method. By default a metric's name is the humanized class name (`RegisteredUsers` →
"Registered Users").

### Available methods

All metric types:

- `name(string $name)`
- `description(string $description)`
- `prefix(string $prefix)`
- `suffix(string $suffix)`
- `precision(int $precision = 0, RoundingMode $mode = RoundingMode::HalfAwayFromZero)`
- `range(Period|string $range, ?string $customRangeStart = null, ?string $customRangeEnd = null)`
- `ranges(): array` — the list of available range keys/labels
- `formatUsing(Closure(float): string $formatter)` — see [Number formatting](#number-formatting)
- `cache()`, `cacheFor()`, `cacheKey()`, `dontCache()` — see [Caching](#caching)

Value & Progress:

- `withChangeAgainstPreviousPeriod(bool $withChange = true)`

Progress:

- `target(float $target)`
- `shouldBeAvoided(bool $avoid = true)`

Trend (unit helpers):

- `unit(Unit|string $unit)` — a `Unit` case or `MINUTE`, `HOUR`, `DAY`, `WEEK`, `MONTH`, `YEAR`
- `perMinute()`, `hourly()`, `daily()`, `weekly()`, `monthly()`, `yearly()`

> `precision()` takes PHP's native `RoundingMode` enum, e.g.
> `->precision(2, RoundingMode::HalfEven)`.

## Enums

Ranges and units are backed by string enums for IDE autocomplete and type safety. Strings are
still accepted everywhere, so existing code keeps working:

```php
use RoundlyConsulting\Metrics\Enums\Period;
use RoundlyConsulting\Metrics\Enums\Unit;

$metric->range(Period::MonthToDate);   // identical to ->range('MTD')
$metric->unit(Unit::Hour);             // identical to ->unit('HOUR')
$metric->range(Period::Custom, '2024-01-01 00:00:00', '2024-03-01 00:00:00');
```

## Aggregate helpers

Use these inside `calculate()` (or via the inline builders) to compute the result from an
Eloquent query.

**Value, Progress and Trend** — `$column` is the aggregated column, `$dateColumn` is the
column used for period filtering (defaults to the model's `created_at`):

- `count(Builder $query, ?string $column = null, ?string $dateColumn = null)`
- `average(...)`, `sum(...)`, `max(...)`, `min(...)` — same signature

> For **Trend** metrics, `$column` is required (it is the value aggregated over time).

**Partition** — `$groupBy` is the column to group by, `$valueColumn` is the aggregated column
(defaults to `$groupBy`), `$dateColumn` defaults to `created_at`:

- `count(Builder $query, string $groupBy, ?string $valueColumn = null, ?string $dateColumn = null)`
- `average(...)`, `sum(...)`, `max(...)`, `min(...)` — same signature

## Date ranges

Filter any metric with `range()`. The available keys come from `ranges()` /
`Period::options()`:

| Key | Label | Key | Label |
|---|---|---|---|
| `7` | 7 Days | `THIS_WEEK` | This Week |
| `14` | 14 Days | `LAST_WEEK` | Last Week |
| `30` | 30 Days | `THIS_MONTH` | This Month |
| `60` | 60 Days | `LAST_MONTH` | Last Month |
| `90` | 90 Days | `THIS_QUARTER` | This Quarter |
| `365` | 365 Days | `LAST_QUARTER` | Last Quarter |
| `YESTERDAY` | Yesterday | `THIS_YEAR` | This Year |
| `TODAY` | Today | `LAST_YEAR` | Last Year |
| `WTD` | Week To Date | `CUSTOM` | Custom |
| `MTD` | Month To Date | `ALL` | All |
| `QTD` | Quarter To Date | | |
| `YTD` | Year To Date | | |

```php
$metric->range('MTD');                 // month to date
$metric->range(Period::ThisQuarter);   // the full current quarter
$metric->range('CUSTOM', '2024-01-01 00:00:00', '2024-03-01 00:00:00');
```

Ranges resolve in `config('metrics.timezone')` (falling back to the app timezone). Labels run
through Laravel's translator, so they can be localised in your app's translation files.

## Trend units

Trend results are grouped by a date column formatted to the chosen unit (default `DAY`).
Available units are `MINUTE`, `HOUR`, `DAY`, `WEEK`, `MONTH`, and `YEAR`. Grouping uses a
database-specific SQL date expression: **MySQL, MariaDB, PostgreSQL, and SQLite** are
supported out of the box. To support another driver, map it in
`config('metrics.trend_drivers')` or register an implementation of
`RoundlyConsulting\Metrics\Types\Trend\QueryExpressions\QueryExpression` in
`Trend::$queryExpressions` keyed by the driver name.

## Caching

Caching is **off by default**. Enable it globally via config, or per metric:

```php
Metric::value()->count(User::query())->range('TODAY')->cacheFor(now()->addHour())->toArray();

$metric->cache(120);          // cache for 120 seconds
$metric->cacheKey('users');   // override the derived cache key
$metric->dontCache();         // force a fresh computation
```

The cache key is derived from the metric class, range, unit, and target, so different
configurations never collide. Only the `result` portion is cached.

## Number formatting

Present values as currency, percentages, or abbreviated figures without touching your
front-end. The formatted output appears under a `formatted` key alongside the raw value:

```php
use Illuminate\Support\Number;

Metric::value()
    ->sum(Order::query(), 'total')
    ->formatUsing(fn (float $value): string => Number::currency($value, 'USD'))
    ->toArray();
// result.value => 1500.0, result.formatted => "$1,500.00"
```

For trends and partitions the formatter is applied to every point.

## Typed result accessors

Result objects expose typed getters for chart consumers, in addition to `toArray()`:

- `ValueResult`: `value()`, `previous()`, `change()`, `isIncrease()`
- `TrendResult`: `trends()`, `labels()`, `values()`
- `ProgressResult`: `value()`, `progress()`, `target()`, `previous()`, `isIncrease()`
- `PartitionResult`: `partitions()`, `labels()`, `values()`

## Configuration

The published `config/metrics.php` documents every key:

| Key | Type | Default | Env |
|---|---|---|---|
| `timezone` | `?string` | `null` (app timezone) | `METRICS_TIMEZONE` |
| `default_range` | `string` | `ALL` | — |
| `default_unit` | `string` | `DAY` | — |
| `precision` | `int` | `0` | — |
| `cache.enabled` | `bool` | `false` | `METRICS_CACHE_ENABLED` |
| `cache.store` | `?string` | `null` (default store) | `METRICS_CACHE_STORE` |
| `cache.ttl` | `int` | `300` | `METRICS_CACHE_TTL` |
| `cache.prefix` | `string` | `metrics` | — |
| `trend_drivers` | `array` | mysql/mariadb/pgsql/sqlite | — |

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security
vulnerabilities.

## Credits

- [Andrej Mihaliak](https://github.com/mihaliak)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
