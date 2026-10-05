<!-- roundly-hero:start -->
<p align="center">
  <a href="https://roundly-consulting.com/open-source/docs/metrics-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=metrics-for-laravel">
    <img src="https://raw.githubusercontent.com/roundly-consulting/metrics-for-laravel/main/art/hero.png" alt="Metrics for Laravel — Roundly open source" width="100%">
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

Turn any Eloquent query into a ready-to-render dashboard metric: a single value with
period-over-period change, a time series for charts, progress against a target, or a partitioned
breakdown. Date ranges, timezones, aggregation, caching and formatting are handled for you, and the
result comes back as clean JSON for any front-end — no admin panel required.

## Installation

Requires PHP 8.4 and Laravel 12 or 13.

```bash
composer require roundly-consulting/metrics-for-laravel
```

## Usage

Build a metric inline from any query — `toArray()` is the JSON-ready envelope:

```php
use App\Models\Order;
use App\Models\User;
use RoundlyConsulting\Metrics\Enums\Period;
use RoundlyConsulting\Metrics\Facades\Metrics;

Metrics::value()->count(User::query())
    ->range(Period::Today)
    ->withChangeAgainstPreviousPeriod()
    ->toArray();

Metrics::trend()->count(User::query(), 'created_at')->daily()->range(Period::MonthToDate)->toArray();
Metrics::progress()->sum(Order::query(), 'total')->target(10000)->range(Period::MonthToDate)->toArray();
Metrics::partition()->count(User::query(), 'plan')->limit(5)->toArray();
```

Or read the typed result in PHP:

```php
$revenue = Metrics::value()->sum(Order::query(), 'total')->range(Period::ThisMonth)->result();

$revenue->value();   // 15230.0
```

Register metrics under keys and return a whole dashboard from one endpoint:

```php
Metrics::register('signups', fn () => Metrics::value()->name('Sign-ups')->count(User::query()));
Metrics::register('revenue', fn () => Metrics::progress()->name('Revenue')->sum(Order::query(), 'total')->target(10000));

return Metrics::dashboard(['signups', 'revenue'])->range(Period::ThisMonth);   // Responsable: JSON
```

<!-- roundly-docs:start -->
## Documentation

The full documentation — configuration, every feature and its API, and testing — lives on our
website: **[roundly-consulting.com/open-source/docs/metrics-for-laravel](https://roundly-consulting.com/open-source/docs/metrics-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=metrics-for-laravel)**

Release notes are in [CHANGELOG.md](CHANGELOG.md). To contribute, see the
[contributing guide](https://github.com/roundly-consulting/.github/blob/main/CONTRIBUTING.md).
<!-- roundly-docs:end -->

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
