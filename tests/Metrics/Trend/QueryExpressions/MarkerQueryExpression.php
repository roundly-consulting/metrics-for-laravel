<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Tests\Metrics\Trend\QueryExpressions;

use RoundlyConsulting\Metrics\Types\Trend\QueryExpressions\QueryExpression;
use RuntimeException;

final class MarkerQueryExpression implements QueryExpression
{
    public function toSql(string $unit, string $column): string
    {
        throw new RuntimeException('marker driver used');
    }

    public function addMinutes(string $column, int $minutes): string
    {
        throw new RuntimeException('marker driver used');
    }
}
