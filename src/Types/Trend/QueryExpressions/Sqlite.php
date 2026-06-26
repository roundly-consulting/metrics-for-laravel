<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Types\Trend\QueryExpressions;

use RoundlyConsulting\Metrics\Exceptions\InvalidUnitException;

final class Sqlite implements QueryExpression
{
    public function toSql(string $unit, string $column): string
    {
        return match ($unit) {
            'YEAR' => "strftime('%Y', datetime({$column}))",
            'MONTH' => "strftime('%Y-%m', datetime({$column}))",
            'WEEK' => "strftime('%Y-%W', datetime({$column}))",
            'DAY' => "strftime('%Y-%m-%d', datetime({$column}))",
            'HOUR' => "strftime('%Y-%m-%d %H:00', datetime({$column}))",
            'MINUTE' => "strftime('%Y-%m-%d %H:%M:00', datetime({$column}))",
            default => throw InvalidUnitException::for($unit),
        };
    }
}
