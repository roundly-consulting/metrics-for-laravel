<!-- roundly-hero:start -->
<p align="center">
  <a href="https://roundly-consulting.com/open-source/docs/metrics-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=metrics-for-laravel">
    <img src="art/hero.png" alt="Metrics for Laravel — Roundly open source" width="100%">
  </a>
</p>
<!-- roundly-hero:end -->

<!-- roundly-badges:start -->
<p align="center">
  <a href="https://packagist.org/packages/roundly-consulting/metrics-for-laravel"><img src="https://img.shields.io/packagist/v/roundly-consulting/metrics-for-laravel?style=flat-square&label=release" alt="Latest release"></a>
  <a href="https://github.com/roundly-consulting/metrics-for-laravel/actions/workflows/run-tests.yml"><img src="https://img.shields.io/github/actions/workflow/status/roundly-consulting/metrics-for-laravel/run-tests.yml?branch=main&style=flat-square&label=tests" alt="Tests"></a>
  <a href="https://github.com/roundly-consulting/metrics-for-laravel/actions/workflows/fix-php-code-style-issues.yml"><img src="https://img.shields.io/github/actions/workflow/status/roundly-consulting/metrics-for-laravel/fix-php-code-style-issues.yml?branch=main&style=flat-square&label=code%20style" alt="Code style"></a>
  <a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/donate-support%20our%20open%20source-F24E29?style=flat-square&logo=stripe&logoColor=white" alt="Donate"></a>
  <a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/patreon-become%20a%20patron-F96854?style=flat-square&logo=patreon&logoColor=white" alt="Patreon"></a>
  <a href="https://roundly-consulting.com/support-us?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=metrics-for-laravel#crypto"><img src="https://img.shields.io/badge/crypto-BTC%20%C2%B7%20ETH%20%C2%B7%20BNB%20%C2%B7%20SOL-F7931A?style=flat-square&logo=bitcoin&logoColor=white" alt="Crypto"></a>
</p>
<!-- roundly-badges:end -->

# Metrics for Laravel

Calculate value, trend, progress, and partition metrics from any Eloquent query.

`metrics-for-laravel` turns an Eloquent query into a ready-to-render metric — a single
value with period-over-period change, a time series for charts, a progress bar against a
target, or a partitioned breakdown for pie/bar charts. Build a metric inline in one fluent
expression, or define a small reusable class. The package handles date ranges, aggregation,
grouping, caching, and formatting, and hands back clean JSON-friendly data — dashboard
metrics without an admin panel, for any front-end.

## Requirements

- PHP 8.4+
- Laravel 12 or 13

## Installation

```bash
composer require roundly-consulting/metrics-for-laravel
```

The service provider and the `Metrics` facade are auto-discovered. The package works against
your existing models and tables — there are no migrations.

Optionally publish the config file:

```bash
php artisan vendor:publish --tag="metrics-config"
```

## Quick start

Build a metric inline with the `Metrics` facade — no class required:

```php
use App\Models\User;
use RoundlyConsulting\Metrics\Enums\Period;
use RoundlyConsulting\Metrics\Facades\Metrics;

$data = Metrics::value()
    ->count(User::query())
    ->range(Period::Today)
    ->withChangeAgainstPreviousPeriod()
    ->toArray();
```

The facade exposes one builder per metric type — `Metrics::value()`, `Metrics::trend()`,
`Metrics::progress()`, and `Metrics::partition()`:

```php
Metrics::trend()->count(User::query(), 'created_at')->daily()->range(Period::MonthToDate)->toArray();
Metrics::progress()->sum(Order::query(), 'total')->target(10000)->range(Period::MonthToDate)->toArray();
Metrics::partition()->count(User::query(), 'plan')->toArray();
```

## Reading a result

Every metric gives you two views of the same calculation:

- `result()` — the **typed result object** (`ValueResult`, `TrendResult`, `ProgressResult` or
  `PartitionResult`), for PHP code that works with the numbers;
- `toArray()` — the JSON-ready envelope (name, range, formatted result) for a front-end.

```php
$revenue = Metrics::value()->sum(Order::query(), 'total')->range(Period::ThisMonth)->result();

$revenue->value();       // 15230.0
$revenue->change();      // percentage change, when a comparison was requested

Metrics::trend()->count(User::query(), 'id')->daily()->result()->values();   // list<float>
```

The typed bases narrow the return type, so `result()` on a `Value` metric is a `ValueResult`, on
a `Progress` metric a `ProgressResult` (which is also a `ValueResult`), and so on. Both views go
through the result cache.

## Metric types (reusable classes)

For metrics you reuse across a dashboard, define a class by extending one of the typed bases
(all of which extend `RoundlyConsulting\Metrics\Metric`) and implementing `calculate()`, which
returns the matching result object.

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
use RoundlyConsulting\Metrics\Facades\Metrics;

// in a service provider boot()
Metrics::register('registered_users', RegisteredUsers::class);
Metrics::register('revenue', MonthlyRevenueGoal::class);
Metrics::register('users_by_plan', fn () => UsersByPlan::make());

// in a controller
return response()->json(
    collect(Metrics::all())->map->toArray()
);

// or a single metric by key, applying the request's period
return response()->json(
    Metrics::get($key)->range($request->input('period', 'TODAY'))->toArray()
);
```

Register a class-string, an instance, or a closure. Every `Metrics::get()` returns a new
metric — a registered instance is copied — so a `range()` or `timezone()` applied to what it
returns never leaks into the next call (or, under Octane, the next request). `Metrics::get()`
throws `RoundlyConsulting\Metrics\Exceptions\UnknownMetricException` for an unregistered key.
`Metrics::keys()` lists the registered keys without resolving them, `Metrics::has('revenue')`
checks one, and `Metrics::unregister('revenue')` removes one (a no-op for an unknown key).

```php
Metrics::get('revenue')->result()->value();   // the typed result of a registered metric
```

| Method | Returns | Purpose |
|---|---|---|
| `register(string $key, class-string\|Metric\|Closure $metric)` | `MetricsManager` | add a metric to the registry (chainable) |
| `unregister(string $key)` | `MetricsManager` | remove it (chainable) |
| `get(string $key)` | `Metric` | resolve one (a fresh copy), tagged with its key |
| `has(string $key)` / `keys()` / `all()` | `bool` / `list<string>` / `array<string, Metric>` | inspect the registry |
| `dashboard(array $keys)` | `Dashboard` | resolve many at once |
| `value()` / `trend()` / `progress()` / `partition()` | a pending builder | build a metric inline |
| `forget(string\|Metric $metric)` | `void` | forget one metric's cached results |
| `flushCache()` | `void` | forget every cached result |

## Without the facade

The facade is a thin layer over `RoundlyConsulting\Metrics\MetricsManager`, a container
singleton. Inject it to use the same API — `Metrics::fake()` swaps the injected instance too:

```php
use RoundlyConsulting\Metrics\MetricsManager;

final class DashboardController
{
    public function __construct(private MetricsManager $metrics) {}

    public function __invoke(): array
    {
        return [
            'revenue' => $this->metrics->get('revenue')->result()->value(),
            'signups' => $this->metrics->value()->count(User::query())->range('TODAY')->result()->value(),
        ];
    }
}
```

The metric classes work on their own too — no registry, no facade:

```php
RegisteredUsers::make()->range('TODAY')->result();   // ValueResult
app(RegisteredUsers::class)->toArray();             // resolved through the container
```

Metrics is a calculation package, so it has no action classes: the builders and your metric
classes are the behaviour, and the manager only registers, resolves and invalidates them.

## Dashboards (batch resolution)

Resolve many registered metrics at once into a single keyed envelope, sharing one range
(and, optionally, one timezone):

```php
use RoundlyConsulting\Metrics\Enums\Period;
use RoundlyConsulting\Metrics\Facades\Metrics;

return Metrics::dashboard(['users', 'revenue', 'churn'])
    ->range(Period::ThisMonth)
    ->toArray();
// ['users' => [...envelope...], 'revenue' => [...], 'churn' => [...]]
```

The dashboard is `Responsable`, so a controller can `return Metrics::dashboard([...])->range(...)`
directly and get the JSON envelope.

## Returning metrics from controllers

Metrics and dashboards implement `Illuminate\Contracts\Support\Responsable`, so you can return
one straight from a controller and get its JSON envelope — authorization stays your app's
responsibility:

```php
public function show(string $key)
{
    return Metrics::get($key)->range(request('period', 'TODAY'));
}
```

## Comparison periods

`Value` and `Progress` metrics expose period-over-period change. By default the comparison is
the immediately-preceding window; `compareTo()` compares against any range instead:

```php
use RoundlyConsulting\Metrics\Enums\Period;

Metrics::value()
    ->count(Order::query())
    ->range(Period::ThisMonth)
    ->compareTo(Period::LastYear)   // vs. the same metric a year ago
    ->toArray();
```

## Top N partitions

Cap a partition to its largest groups and roll the rest into a single bucket:

```php
Metrics::partition()
    ->count(User::query(), 'country')
    ->limit(5)                       // 5 groups + "Other"
    ->otherLabel('Elsewhere')        // optional; defaults to the translatable "Other"
    ->toArray();
```

The bucket is the rest rolled up with the metric's own aggregate: summed for `count()` and
`sum()`, the largest value for `max()`, the smallest for `min()`, and the average of all the
remaining rows (not of the group averages) for `average()`. A real group whose key equals the
bucket's label is never one of the top N — it joins the bucket, so no group is overwritten.

Map raw group keys to display labels with `labelUsing()` — raw keys stay available via the
result's `keys()`, and the labels appear under a `labels` key:

```php
Metrics::partition()
    ->count(User::query(), 'country_id')
    ->labelUsing(fn (int|string $key): string => Country::name($key))
    ->toArray();
// result => ['partitions' => ['1' => 40.0, ...], 'labels' => ['1' => 'Germany', ...]]
```

## Console commands

Inspect your registered metrics from the CLI:

```bash
php artisan metrics:list              # every registered key and its class
php artisan metrics:show users        # resolve "users" and print its envelope
php artisan metrics:show users --range=MTD
```

## Events

A `RoundlyConsulting\Metrics\Events\MetricCalculated` event fires every time a metric resolves —
useful for instrumentation and slow-metric logging. It carries the registry `key` (null for
ad-hoc metrics), the `range`, the `durationMs`, and whether it was served `fromCache`:

```php
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Metrics\Events\MetricCalculated;

Event::listen(function (MetricCalculated $event): void {
    if ($event->durationMs > 500) {
        logger()->warning("Slow metric [{$event->key}] took {$event->durationMs}ms");
    }
});
```

## Testing helper

`Metrics::fake()` swaps the manager — behind the facade and in the container, so injected
managers get it too — for a recording `RoundlyConsulting\Metrics\Testing\MetricsFake`. It
keeps every metric you had registered, answers the keys you pass with canned results, and
records what was resolved and invalidated — mirroring Laravel's `Http::fake()`:

```php
use RoundlyConsulting\Metrics\Facades\Metrics;

$fake = Metrics::fake([
    'active-users' => 1200,                  // a number becomes a value envelope
    'revenue'      => ['value' => 5000.0],   // an array is used as the raw result
]);

// ...exercise the code under test, e.g.:
Metrics::get('active-users')->toArray();
Metrics::forget('revenue');

Metrics::assertResolved('active-users');
$fake->assertResolvedTimes('active-users', 1);
$fake->assertNotResolved('revenue');

// forget() and flushCache() are recorded, and the cache is left alone
$fake->assertForgotten('revenue');          // by key, or by class for an unregistered metric
$fake->assertNotForgotten('active-users');
$fake->assertCacheNotFlushed();
```

The opposite assertions are there for the tests where nothing should happen:

```php
$fake->assertNothingResolved();    // no metric was resolved at all
$fake->assertNothingForgotten();   // forget() was never called
$fake->assertCacheFlushed();       // flushCache() was called
```

Canned values may be a number, an array (raw result), a `Result`, or a full `Metric`
instance. Canned results are never cached — a canned `Metric` instance is used as a copy with
caching switched off — so one test's canned value can't leak into the next.

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
- `timezone(?string $timezone)` — resolve this metric's ranges and label its trend buckets in
  an explicit timezone, overriding `config('metrics.timezone')` and the app timezone (see
  [Timezones](#timezones))
- `ranges(): array` — the list of available range keys/labels
- `formatUsing(Closure(float): string $formatter)` — see [Number formatting](#number-formatting)
- `cache()`, `cacheFor()`, `cacheKey()`, `dontCache()` — see [Caching](#caching)

Value & Progress:

- `withChangeAgainstPreviousPeriod(bool $withChange = true)`
- `compareTo(Period|string $range, ?string $customRangeStart = null, ?string $customRangeEnd = null)` —
  compare against an arbitrary range instead of the immediately-previous period (implies change)

Progress:

- `target(float $target)`
- `shouldBeAvoided(bool $avoid = true)`

Trend (unit helpers):

- `unit(Unit|string $unit)` — a `Unit` case or `MINUTE`, `HOUR`, `DAY`, `WEEK`, `MONTH`, `YEAR`
- `perMinute()`, `hourly()`, `daily()`, `weekly()`, `monthly()`, `yearly()`
- `withoutGapFilling()` — return only buckets that have rows (see [Trend units](#trend-units))
- `groupBy(string $column)` — split into multiple series (see [Trend units](#trend-units))

Partition:

- `limit(int $limit)` — cap to the top N groups, rolling the rest into an "Other" bucket
- `otherLabel(string $label)` — override the "Other" bucket label
- `labelUsing(Closure(int|string): string $resolver)` — map raw group keys to display labels

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

Both enums use the shared [`roundly-consulting/enums-for-laravel`](https://github.com/roundly-consulting/enums-for-laravel)
helper trait, so they expose a full toolkit for building host selects and validation rules:

```php
Period::toOptions()->all();   // ['7' => '7 Days', ..., 'ALL' => 'All'] — value => label map
Period::options();            // Collection<EnumOption> of {value, label, name} DTOs for JS/Inertia
Period::validationRule();     // 'in:7,14,30,...,ALL'
Unit::toOptions()->all();     // ['MINUTE' => 'Minute', ..., 'YEAR' => 'Year']
Unit::validationRule();       // 'in:MINUTE,HOUR,DAY,WEEK,MONTH,YEAR'
Unit::labels();               // Collection<string> of readable labels
```

## Aggregate helpers

Use these inside `calculate()` (or via the inline builders) to compute the result from an
Eloquent query.

**Value, Progress and Trend** — `$column` is the aggregated column, `$dateColumn` is the
column used for period filtering (defaults to the model's `created_at`):

- `count(Builder $query, ?string $column = null, ?string $dateColumn = null)`
- `average(...)`, `sum(...)`, `max(...)`, `min(...)` — same signature

> For **Trend** metrics, `$column` is required (it is the value aggregated over time).

**Partition** — `$groupBy` is the column to group by, `$valueColumn` is the aggregated column,
`$dateColumn` defaults to `created_at`. Without a `$valueColumn`, `count()` counts rows — so the
`NULL` group (keyed `''`) reports its real size — and the other aggregates use `$groupBy`:

- `count(Builder $query, string $groupBy, ?string $valueColumn = null, ?string $dateColumn = null)`
- `average(...)`, `sum(...)`, `max(...)`, `min(...)` — same signature

## Date ranges

Filter any metric with `range()`. The available keys come from `ranges()` /
`Period::toOptions()`:

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

Weeks are ISO weeks — Monday to Sunday — whatever the app locale, the same weeks the `WEEK`
trend buckets count. Quarter and year-to-date comparisons never overflow on month-end days
(on 12-31, `LAST_QUARTER` is Q3; on a leap day, the previous `YTD` ends on 02-28). The previous
period of a `CUSTOM` range is the window of exactly the same length that ends one second before
it starts. Labels run through Laravel's translator, so they can be localised in your app's
translation files.

### Timezones

A metric has a **reporting timezone** — `timezone()` on the metric (or `Dashboard::timezone()`),
else `config('metrics.timezone')`, else the app timezone. Ranges are resolved in it (`TODAY` is
today there, and `CUSTOM` bounds are read as its wall clock), and trend buckets are labelled in
it.

Timestamps are taken to be stored in the **app timezone** (`config('app.timezone')`), which is
how Eloquent writes them. Range bounds are converted to it before they reach the query, and a
trend moves each row's timestamp onto the reporting clock before bucketing it — daylight-saving
changes of either zone included — so a row stored at `2026-09-14 23:30` UTC counts as
`2026-09-15` in `Europe/Bratislava`:

```php
config(['metrics.timezone' => 'Europe/Bratislava']);   // app timezone: UTC

Metrics::value()->count(User::query())->range('TODAY')->result()->value();
Metrics::trend()->count(User::query(), 'id')->hourly()->range('TODAY')->result()->trends();
// ['2026-09-15 00:00' => ..., '2026-09-15 01:00' => ..., ...] — local hours
```

A `date` column (no time of day) has no timezone to convert from; aggregate one with the
reporting timezone left at the app timezone.

## Trend units

Trend results are grouped by a date column formatted to the chosen unit (default `DAY`).
Available units are `MINUTE`, `HOUR`, `DAY`, `WEEK`, `MONTH`, and `YEAR`. Grouping uses a
database-specific SQL date expression: **MySQL, MariaDB, PostgreSQL, and SQLite** are
supported out of the box. To support another driver, implement
`RoundlyConsulting\Metrics\Types\Trend\QueryExpressions\QueryExpression` — `toSql()` formats a
column into the unit's bucket key, `addMinutes()` shifts a column onto the reporting clock — and
map it in `config('metrics.trend_drivers')` under the driver name (at runtime:
`config(['metrics.trend_drivers.oracle' => OracleExpression::class])`). The config map is the
only driver registry, used by class-based trends and `Metrics::trend()` alike.

### Gap filling

Every bucket in the selected range is present in the output — empty buckets are filled with
`0` so charts have a continuous axis. The axis starts at the bucket that holds the range start
(the ISO week of January 1st for a weekly `YTD`, the month of the start for a monthly `90`), so
the bucket holding "now" is always there. Opt out with `withoutGapFilling()` to return only the
buckets that actually have rows:

```php
Metrics::trend()->count(User::query(), 'created_at')->daily()->withoutGapFilling()->toArray();
```

### Multi-series trends

Split a trend by a dimension with `groupBy()`. The combined totals stay under `trends`, and
an additive `series` key holds one bucket set per dimension value:

```php
Metrics::trend()->count(User::query(), 'created_at')->daily()->groupBy('plan')->toArray();
// result => [
//   'trends' => ['2024-01-01' => 30.0, ...],            // totals across series
//   'series' => [
//     'pro'  => ['2024-01-01' => 20.0, ...],
//     'free' => ['2024-01-01' => 10.0, ...],
//   ],
// ]
```

Each series is gap-filled across the same range, so every series shares the same buckets.

## Caching

Caching is **off by default**. Enable it globally via config, or per metric:

```php
Metrics::value()->count(User::query())->range('TODAY')->cacheFor(now()->addHour())->toArray();

$metric->cache(120);          // cache for 120 seconds
$metric->cacheKey('users');   // override the derived cache key
$metric->dontCache();         // force a fresh computation
```

The cache key is derived from the metric class, its registry key, the range, the reporting and
app timezones, the precision and rounding mode, the type's options (unit, target, comparison,
limit, the translated "Other" label…) and — for inline builders — the query and aggregate, so
different metrics never collide. Only the `result` portion is cached, as plain data: `result()`
rebuilds the typed object from it. Presentation is applied on every read and never cached —
`formatUsing()` and a partition's `labelUsing()` labels.

### Forgetting cached results

```php
Metrics::forget('revenue');       // every cached range/timezone/option of one registered metric
Metrics::forget($metric);         // an unregistered metric instance (by its class)
Metrics::flushCache();            // every cached metric result
```

Invalidation works on every cache store, with or without tags: each entry records the cache
generation it was written under, and forgetting starts a new generation, so older entries are
recalculated on their next read and overwritten in place. It covers custom `cacheKey()` entries
too. `forget()` throws `UnknownMetricException` for a key that isn't registered.

Globally, `METRICS_CACHE_ENABLED` accepts `true`/`false`/`1`/`0`/`on`/`off`/`yes`/`no`, and
`METRICS_CACHE_TTL` is a whole number of seconds between `1` and `31536000` (one year) — the
string `.env` produces is honoured. An unusable TTL or switch value (say
`METRICS_CACHE_ENABLED=disabled`) throws
`RoundlyConsulting\Metrics\Exceptions\InvalidConfigurationException` instead of silently
falling back to the default.

## Number formatting

Present values as currency, percentages, or abbreviated figures without touching your
front-end. The formatted output appears under a `formatted` key alongside the raw value:

```php
use Illuminate\Support\Number;

Metrics::value()
    ->sum(Order::query(), 'total')
    ->formatUsing(fn (float $value): string => Number::currency($value, 'USD'))
    ->toArray();
// result.value => 1500.0, result.formatted => "$1,500.00"
```

For trends and partitions the formatter is applied to every point.

## Typed result accessors

`$metric->result()` returns the result object; it exposes typed getters for chart consumers,
in addition to `toArray()`. `Result::fromArray()` rebuilds one from its array form:

- `ValueResult`: `value()`, `previous()`, `change()`, `isIncrease()`
- `TrendResult`: `trends()`, `series()`, `labels()`, `values()`
- `ProgressResult` (a `ValueResult`): `value()`, `progress()`, `target()`, `previous()`,
  `previousProgress()`, `change()`, `avoid()`, `isIncrease()`
- `PartitionResult`: `partitions()`, `labels()`, `keys()`, `values()`

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
| `cache.ttl` | `int` (1–31536000 s; digit strings accepted) | `300` | `METRICS_CACHE_TTL` |
| `cache.prefix` | `string` | `metrics` | — |
| `partition.other_label` | `string` | `Other` | — |
| `trend_drivers` | `array` | mysql/mariadb/pgsql/sqlite | — |

Every key is read strictly and throws `RoundlyConsulting\Metrics\Exceptions\InvalidConfigurationException`
naming it when present but unusable; only an unset (`null`) key takes its default.
`default_range` must be a `Period` value (`ALL`, `TODAY`, `30`, …) and `default_unit` a `Unit`
value (`MINUTE` … `YEAR`, exact case); `precision` must be an integer (digit strings accepted);
`timezone`, `cache.store`, `cache.prefix` and `partition.other_label` must be non-empty strings;
every `trend_drivers` entry must name a `QueryExpression` class. A typo is never silently ignored.

## Integrates with

- **[`roundly-consulting/enums-for-laravel`](https://github.com/roundly-consulting/enums-for-laravel)**
  (required) — the `Period` and `Unit` enums adopt its `Helpers` trait, giving you
  `options()`/`toOptions()`/`labels()`/`values()`/`names()`/`validationRule()` plus name- and
  label-based case lookups for host selects and validation. See [Enums](#enums).
- **[`roundly-consulting/package-toolkit-for-laravel`](https://github.com/roundly-consulting/package-toolkit-for-laravel)**
  (required) — the package is bootstrapped with the toolkit's `PackageServiceProvider`, so its
  config, publish tag and console commands are wired through the shared builder, and
  `php artisan about` reports the registered metric count, the result-cache state and the default
  range.

### Recipe: an event → metric sink

`metrics-for-laravel` reads from your existing tables, so any other package's domain events can
feed a metric with **no extra dependency** — this is host wiring, not a package `require`. Point a
metric at the model the event writes, or record a lightweight counter and count that:

```php
use App\Models\Order;
use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Metrics\Facades\Metrics;

// Register a metric over whatever table the events already populate.
Metrics::register('orders_today', fn () => Metrics::value()->count(Order::query())->range('TODAY'));

// Or fan a package's event into your own metrics table, then build a metric over it.
Event::listen(OrderPlaced::class, function (OrderPlaced $event): void {
    MetricEvent::create(['name' => 'order_placed', 'occurred_at' => now()]);
});

Metrics::register('orders_placed', fn () => Metrics::trend()
    ->count(MetricEvent::query()->where('name', 'order_placed'), 'occurred_at')
    ->daily());
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](https://github.com/roundly-consulting/.github/blob/main/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security
vulnerabilities.

## Credits

- [Andrej Mihaliak](https://github.com/mihaliak)
- [All Contributors](../../contributors)

<!-- roundly-support:start -->
## Support our work

This package is free and open source, built and maintained by
[Roundly Consulting](https://roundly-consulting.com/open-source?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=metrics-for-laravel).
If it saves you time, please consider supporting our open-source work — a one-time donation, a
monthly pledge on Patreon or a crypto donation helps fund maintenance, new features and new
packages.

<a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/Donate-Support%20Roundly%20open%20source-F24E29?style=for-the-badge&logo=stripe&logoColor=white" alt="Donate to Roundly open source"></a>
<a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/Patreon-Become%20a%20patron-F96854?style=for-the-badge&logo=patreon&logoColor=white" alt="Become a patron on Patreon"></a>
<a href="https://roundly-consulting.com/support-us?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=metrics-for-laravel#crypto"><img src="https://img.shields.io/badge/Crypto-BTC%20%C2%B7%20ETH%20%C2%B7%20BNB%20%C2%B7%20SOL-F7931A?style=for-the-badge&logo=bitcoin&logoColor=white" alt="Donate crypto: BTC, ETH, BNB or SOL"></a>
<!-- roundly-support:end -->

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
