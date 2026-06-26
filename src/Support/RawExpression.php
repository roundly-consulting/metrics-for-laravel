<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Support;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Grammar;

/**
 * A raw SQL fragment built from grammar-escaped identifiers.
 *
 * Implements Laravel's Expression contract directly (rather than `DB::raw()`)
 * so the package can pass dynamically composed — but safely escaped — aggregate
 * and grouping SQL to the query builder.
 */
final class RawExpression implements Expression
{
    public function __construct(private readonly string $value) {}

    public function getValue(Grammar $grammar): string
    {
        return $this->value;
    }
}
