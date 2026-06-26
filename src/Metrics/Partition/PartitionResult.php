<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Metrics\Partition;

use RoundlyConsulting\Metrics\Metrics\Result;

final class PartitionResult implements Result
{
    /**
     * @param  array<array-key, float>  $results
     */
    public function __construct(
        protected array $results = []
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'partitions' => $this->results,
        ];
    }
}
