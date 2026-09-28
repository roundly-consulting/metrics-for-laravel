<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Types\Trend\QueryExpressions;

use RoundlyConsulting\Metrics\Enums\Unit;

/**
 * The SQL date grammar trend metrics run on one database driver. Register an implementation
 * for another driver in `config('metrics.trend_drivers')`, keyed by the driver name.
 */
interface QueryExpression
{
    /**
     * SQL that formats `$column` into the unit's bucket key. It must agree, character for
     * character, with {@see Unit::format()}.
     */
    public function toSql(string $unit, string $column): string;

    /**
     * SQL that moves the datetime `$column` by `$minutes` (negative moves it back), used to
     * re-read a stored wall clock on the reporting timezone's clock.
     */
    public function addMinutes(string $column, int $minutes): string;
}
