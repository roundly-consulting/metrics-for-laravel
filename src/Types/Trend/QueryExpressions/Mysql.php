<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Types\Trend\QueryExpressions;

use RoundlyConsulting\Metrics\Exceptions\InvalidUnitException;

final class Mysql implements QueryExpression
{
    public function toSql(string $unit, string $column): string
    {
        $formats = [
            'MINUTE' => '%Y-%m-%d %H:%i:00',
            'HOUR' => '%Y-%m-%d %H:00',
            'DAY' => '%Y-%m-%d',
            // `%x`, not `%Y`. MySQL's `%v` is already an ISO-8601 week (Monday start,
            // 01..53) and its documented partner is `%x`, the ISO week-numbering year;
            // `%Y` is the calendar year, so `%Y-%v` mispaired them at every boundary in
            // exactly the way PHP's `Y-W` did — 2023-01-01 filed as `2023-52` instead of
            // `2022-52`. Aligned with the other two drivers on ISO-8601.
            'WEEK' => '%x-%v',
            'MONTH' => '%Y-%m',
            'YEAR' => '%Y',
        ];

        if (! array_key_exists($unit, $formats)) {
            throw InvalidUnitException::for($unit);
        }

        return "date_format({$column}, '{$formats[$unit]}')";
    }
}
