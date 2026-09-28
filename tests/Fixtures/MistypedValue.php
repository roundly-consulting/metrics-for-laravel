<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Tests\Fixtures;

use RoundlyConsulting\Metrics\Types\Result;
use RoundlyConsulting\Metrics\Types\Trend\TrendResult;
use RoundlyConsulting\Metrics\Types\Value\Value;

/**
 * A value metric whose calculate() breaks the promise of its base type.
 */
final class MistypedValue extends Value
{
    protected function calculate(): Result
    {
        return new TrendResult(['2023-03-10' => 1.0]);
    }
}
