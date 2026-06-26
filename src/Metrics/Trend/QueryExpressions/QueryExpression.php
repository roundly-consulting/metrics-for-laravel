<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Metrics\Trend\QueryExpressions;

interface QueryExpression
{
    public function toSql(string $unit, string $column): string;
}
