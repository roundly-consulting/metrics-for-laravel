<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Types\Trend\QueryExpressions;

use RoundlyConsulting\Metrics\Exceptions\InvalidUnitException;

final class Postgres implements QueryExpression
{
    public function toSql(string $unit, string $column): string
    {
        $format = match ($unit) {
            'MINUTE' => 'YYYY-MM-DD HH24:MI:00',
            'HOUR' => 'YYYY-MM-DD HH24:00',
            'DAY' => 'YYYY-MM-DD',
            'WEEK' => 'IYYY-IW',
            'MONTH' => 'YYYY-MM',
            'YEAR' => 'YYYY',
            default => throw InvalidUnitException::for($unit),
        };

        return "to_char({$column}, '{$format}')";
    }
}
