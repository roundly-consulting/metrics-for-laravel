<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Tests\Metrics\Trend\QueryExpressions;

use RoundlyConsulting\Metrics\Types\Trend\QueryExpressions\QueryExpression;

/**
 * A driver whose bucket keys never match the PHP side's — every row lands under 'x'.
 */
final class ConstantKeyQueryExpression implements QueryExpression
{
    public function toSql(string $unit, string $column): string
    {
        // Derived from the column (an empty substring of it) rather than a bare literal:
        // Postgres refuses a constant in GROUP BY.
        return "('x' || substr(cast({$column} as text), 1, 0))";
    }

    public function addMinutes(string $column, int $minutes): string
    {
        return $column;
    }
}
