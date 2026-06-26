<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Types\Partition;

use RoundlyConsulting\Metrics\Types\Result;

final class PartitionResult implements Result
{
    /**
     * @param  array<array-key, float>  $results
     * @param  array<string, string>  $labels  display labels keyed by raw group key
     */
    public function __construct(
        protected array $results = [],
        protected array $labels = [],
    ) {}

    /**
     * @return array<array-key, float>
     */
    public function partitions(): array
    {
        return $this->results;
    }

    /**
     * Display labels for each group — the resolved labels when a label resolver
     * is set, otherwise the raw group keys.
     *
     * @return list<string>
     */
    public function labels(): array
    {
        if ($this->labels !== []) {
            return array_values($this->labels);
        }

        return array_map(strval(...), array_keys($this->results));
    }

    /**
     * The raw group keys, regardless of any label resolver.
     *
     * @return list<string>
     */
    public function keys(): array
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
        $data = ['partitions' => $this->results];

        if ($this->labels !== []) {
            $data['labels'] = $this->labels;
        }

        return $data;
    }
}
