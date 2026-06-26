<?php

declare(strict_types=1);

use RoundlyConsulting\Metrics\Types\Trend\QueryExpressions\Mysql;
use RoundlyConsulting\Metrics\Types\Trend\QueryExpressions\Postgres;
use RoundlyConsulting\Metrics\Types\Trend\QueryExpressions\Sqlite;

return [

    /*
    |--------------------------------------------------------------------------
    | Reporting timezone
    |--------------------------------------------------------------------------
    |
    | Date ranges are resolved in this timezone. Leave null to use the
    | application's configured timezone.
    |
    */

    'timezone' => env('METRICS_TIMEZONE'),

    /*
    |--------------------------------------------------------------------------
    | Defaults
    |--------------------------------------------------------------------------
    |
    | Applied to every metric before its own setup() runs. "default_range" is
    | a Period value, "default_unit" a Unit value (used by trend metrics).
    |
    */

    'default_range' => 'ALL',

    'default_unit' => 'DAY',

    'precision' => 0,

    /*
    |--------------------------------------------------------------------------
    | Result caching
    |--------------------------------------------------------------------------
    |
    | Caching is OFF by default. When enabled, a metric's computed result is
    | remembered for "ttl" seconds in the given store. Individual metrics can
    | still opt in/out at runtime via cache()/cacheFor()/dontCache().
    |
    */

    'cache' => [
        'enabled' => env('METRICS_CACHE_ENABLED', false),
        'store' => env('METRICS_CACHE_STORE'),
        'ttl' => env('METRICS_CACHE_TTL', 300),
        'prefix' => 'metrics',
    ],

    /*
    |--------------------------------------------------------------------------
    | Trend query expression drivers
    |--------------------------------------------------------------------------
    |
    | Maps a database driver name to the SQL date-grouping grammar used by
    | trend metrics. Override or extend this to support additional drivers.
    |
    */

    'trend_drivers' => [
        'mysql' => Mysql::class,
        'mariadb' => Mysql::class,
        'pgsql' => Postgres::class,
        'sqlite' => Sqlite::class,
    ],

];
