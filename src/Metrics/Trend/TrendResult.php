<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Metrics\Trend;

use RoundlyConsulting\Metrics\Metrics\Result;

final class TrendResult implements Result
{
    /**
     * @param  array<string, float>  $results
     */
    public function __construct(protected array $results = []) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'trends' => $this->results,
        ];
    }
}
