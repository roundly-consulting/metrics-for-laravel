<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Types\Trend;

use RoundlyConsulting\Metrics\Support\Cast;
use RoundlyConsulting\Metrics\Types\Result;

final class TrendResult implements Result
{
    /**
     * @param  array<string, float>  $results
     * @param  array<string, array<string, float>>  $series  buckets keyed by series, when grouped
     */
    public function __construct(
        protected array $results = [],
        protected array $series = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $series = [];

        foreach (is_array($data['series'] ?? null) ? $data['series'] : [] as $name => $buckets) {
            $series[(string) $name] = Cast::floats($buckets);
        }

        return new self(Cast::floats($data['trends'] ?? null), $series);
    }

    /**
     * @return array<string, float>
     */
    public function trends(): array
    {
        return $this->results;
    }

    /**
     * The per-series buckets when the trend is grouped by a dimension.
     *
     * @return array<string, array<string, float>>
     */
    public function series(): array
    {
        return $this->series;
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
        $data = ['trends' => $this->results];

        if ($this->series !== []) {
            $data['series'] = $this->series;
        }

        return $data;
    }
}
