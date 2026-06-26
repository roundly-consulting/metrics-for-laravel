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
target, or a partitioned breakdown for pie/bar charts. You define a small class, implement
one `calculate()` method, and the package handles date ranges, aggregation, grouping, and
formatting.

## Requirements

- PHP 8.4+
- Laravel 12 or 13

## Installation

```bash
composer require roundly-consulting/metrics-for-laravel
```

The service provider is auto-discovered. The package ships **no config, migrations, or
views** — there is nothing to publish. It works against your existing models and tables.

## Metric types

Create a metric by extending the base class for the type you want and implementing
`calculate()`, which returns the matching result object. Inside `calculate()` you can use the
built-in aggregate helpers to compute the result straight from an Eloquent query, or build
the result object yourself (e.g. from cached values).

| Type | Extend | `calculate()` returns | Good for |
|---|---|---|---|
| Value | `RoundlyConsulting\Metrics\Metrics\Value\Value` | `ValueResult` | a single number, optionally vs. the previous period |
| Trend | `RoundlyConsulting\Metrics\Metrics\Trend\Trend` | `TrendResult` | a time series (line/area chart) |
| Progress | `RoundlyConsulting\Metrics\Metrics\Progress\Progress` | `ProgressResult` | current value vs. a target (progress bar) |
| Partition | `RoundlyConsulting\Metrics\Metrics\Partition\Partition` | `PartitionResult` | a grouped breakdown (pie/bar chart) |

### Value

```php
use App\Models\User;
use RoundlyConsulting\Metrics\Metrics\Result;
use RoundlyConsulting\Metrics\Metrics\Value\Value;

final class RegisteredUsers extends Value
{
    protected function calculate(): Result
    {
        return $this->count(User::query());
    }
}
```

```php
$metric = RegisteredUsers::make()->toArray();

// or via the container
$metric = resolve(RegisteredUsers::class)->toArray();

// in a controller, applying a period from the request
public function index(RegisteredUsers $metric, Request $request): JsonResponse
{
    return response()->json(
        $metric->range($request->input('period', 'TODAY'))->toArray()
    );
}
```

### Trend

```php
use App\Models\User;
use RoundlyConsulting\Metrics\Metrics\Result;
use RoundlyConsulting\Metrics\Metrics\Trend\Trend;

final class RegisteredUsersTrend extends Trend
{
    protected function calculate(): Result
    {
        return $this->count(User::query(), 'created_at');
    }
}

// each point for today, by hour
RegisteredUsersTrend::make()->hourly()->range('TODAY')->toArray();

// each point for the month so far, by day
RegisteredUsersTrend::make()->daily()->range('MTD')->toArray();
```

### Progress

A Progress metric compares the current value to a `target`. Use `shouldBeAvoided()` when the
target is a ceiling you want to stay **under** (rendered red on/above target) rather than a
goal to **reach**.

```php
use App\Models\Subscription;
use RoundlyConsulting\Metrics\Metrics\Progress\Progress;
use RoundlyConsulting\Metrics\Metrics\Result;

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
use RoundlyConsulting\Metrics\Metrics\Partition\Partition;
use RoundlyConsulting\Metrics\Metrics\Result;

final class UsersByPlan extends Partition
{
    protected function calculate(): Result
    {
        // count of users grouped by the `plan` column
        return $this->count(User::query(), 'plan');
    }
}
```

## Configuring a metric

Override presentation and behaviour either inline (fluent calls) or inside the metric's
`setup()` method. By default a metric's name is the humanized class name
(`RegisteredUsers` → "Registered Users").

```php
use App\Models\User;
use RoundlyConsulting\Metrics\Metrics\Result;
use RoundlyConsulting\Metrics\Metrics\Value\Value;

final class RegisteredUsers extends Value
{
    protected function setup(): void
    {
        $this->name('Fancy users')
            ->description('How many fancy users registered today vs yesterday')
            ->prefix('Fancy')
            ->suffix('Users')
            ->range('TODAY')
            ->withChangeAgainstPreviousPeriod();
    }

    protected function calculate(): Result
    {
        return $this->count(User::query());
    }
}
```

### Available methods

All metric types:

- `name(string $name)`
- `description(string $description)`
- `prefix(string $prefix)`
- `suffix(string $suffix)`
- `precision(int $precision = 0, RoundingMode $mode = RoundingMode::HalfAwayFromZero)`
- `range(string $range, ?string $customRangeStart = null, ?string $customRangeEnd = null)`
- `ranges(): array` — the list of available range keys/labels

Value & Progress:

- `withChangeAgainstPreviousPeriod(bool $withChange = true)`

Progress:

- `target(float $target)`
- `shouldBeAvoided(bool $avoid = true)`

Trend (unit helpers):

- `unit(string $unit)` — one of `MINUTE`, `HOUR`, `DAY`, `WEEK`, `MONTH`, `YEAR`
- `perMinute()`, `hourly()`, `daily()`, `weekly()`, `monthly()`, `yearly()`

> `precision()` takes PHP's native `RoundingMode` enum, e.g.
> `->precision(2, RoundingMode::HalfEven)`.

## Aggregate helpers

Use these inside `calculate()` to compute the result from an Eloquent query.

**Value, Progress and Trend** — `$column` is the column for the aggregate function,
`$dateColumn` is the column used for period filtering (defaults to the model's `created_at`):

- `count(Builder $query, ?string $column = null, ?string $dateColumn = null)`
- `average(Builder $query, ?string $column = null, ?string $dateColumn = null)`
- `sum(Builder $query, ?string $column = null, ?string $dateColumn = null)`
- `max(Builder $query, ?string $column = null, ?string $dateColumn = null)`
- `min(Builder $query, ?string $column = null, ?string $dateColumn = null)`

> For **Trend** metrics, `$column` is required (it is the value being aggregated over time).

**Partition** — `$groupBy` is the column to group by, `$valueColumn` is the column for the
aggregate (defaults to `$groupBy`), `$dateColumn` defaults to `created_at`:

- `count(Builder $query, string $groupBy, ?string $valueColumn = null, ?string $dateColumn = null)`
- `average(Builder $query, string $groupBy, ?string $valueColumn = null, ?string $dateColumn = null)`
- `sum(Builder $query, string $groupBy, ?string $valueColumn = null, ?string $dateColumn = null)`
- `max(Builder $query, string $groupBy, ?string $valueColumn = null, ?string $dateColumn = null)`
- `min(Builder $query, string $groupBy, ?string $valueColumn = null, ?string $dateColumn = null)`

## Date ranges

Every metric can be filtered to a date range with `range()`. The available keys come from
`ranges()`:

```php
[
    '30' => '30 Days',
    '60' => '60 Days',
    '90' => '90 Days',
    '365' => '365 Days',
    'YESTERDAY' => 'Yesterday',
    'TODAY' => 'Today',
    'MTD' => 'Month To Date',
    'YTD' => 'Year To Date',
    'CUSTOM' => 'Custom',
    'ALL' => 'All',
]
```

```php
$metric->range('MTD');                 // month to date
$metric->range('60');                  // last 60 days
$metric->range('CUSTOM', '2024-01-01 00:00:00', '2024-03-01 00:00:00');
```

Labels are run through Laravel's translator, so you can localise them in your app's
translation files.

## Trend units

Trend results are grouped by a date column formatted according to the chosen unit. The
default is `DAY`, so all rows from the same day fall into a single point. Available units are
`MINUTE`, `HOUR`, `DAY`, `WEEK`, `MONTH`, and `YEAR`.

```php
final class RegisteredUsersTrend extends Trend
{
    protected function setup(): void
    {
        $this->unit('DAY')->range('MTD');
    }

    protected function calculate(): Result
    {
        return $this->count(User::query(), 'created_at');
    }
}
```

> Trend metrics use a database-specific SQL date expression for grouping. MySQL and SQLite
> are supported out of the box; for another driver, register an implementation of
> `RoundlyConsulting\Metrics\Metrics\Trend\QueryExpressions\QueryExpression` in
> `Trend::$queryExpressions` keyed by the driver name.

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
