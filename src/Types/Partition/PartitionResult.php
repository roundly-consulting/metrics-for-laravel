<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Types\Partition;

use RoundlyConsulting\Metrics\Types\Result;

final class PartitionResult implements Result
{
    /**
     * @param  array<array-key, float>  $results
     */
    public function __construct(
        protected array $results = []
    ) {}

    /**
     * @return array<array-key, float>
     */
    public function partitions(): array
    {
        return $this->results;
    }

    /**
     * @return list<string>
     */
    public function labels(): array
    {
        return array_map(strval(...), array_keys($this->results));
    }

    /**
     * @return list<float>
     */
    public function values(): array
    {
        return array_values($this->results);
    }

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
