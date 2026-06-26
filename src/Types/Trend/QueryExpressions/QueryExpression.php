<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Types\Trend\QueryExpressions;

interface QueryExpression
{
    public function toSql(string $unit, string $column): string;
}
