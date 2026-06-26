<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Metrics\Trend\QueryExpressions;

use RoundlyConsulting\Metrics\Exceptions\InvalidUnitException;

final class Mysql implements QueryExpression
{
    public function toSql(string $unit, string $column): string
    {
        $formats = [
            'MINUTE' => '%Y-%m-%d %H:%i:00',
            'HOUR' => '%Y-%m-%d %H:00',
            'DAY' => '%Y-%m-%d',
            'WEEK' => '%Y-%v',
            'MONTH' => '%Y-%m',
            'YEAR' => '%Y',
        ];

        if (! array_key_exists($unit, $formats)) {
            throw InvalidUnitException::for($unit);
        }

        return "date_format({$column}, '{$formats[$unit]}')";
    }
}
